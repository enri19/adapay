<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Services\HotspotProvisioner;
use App\Support\OrderId;

class WebhookController extends Controller
{
  // Ubah status Midtrans → status app
  private function normalizeIncoming(string $txStatus): string {
    $s = strtolower($txStatus);
    if (in_array($s, ['capture','settlement','success'], true)) return 'PAID';
    if (in_array($s, ['pending','authorize'], true)) return 'PENDING';
    if (in_array($s, ['deny','expire','cancel','failure'], true)) return 'FAILED';
    return 'PENDING';
  }

  // aman ubah mixed → array
  private function arr($x): array {
    if (is_array($x)) return $x;
    if (is_string($x)) { $d = json_decode($x, true); if (json_last_error()===JSON_ERROR_NONE) return $d ?: []; }
    return json_decode(json_encode($x), true) ?: [];
  }

  public function handle(Request $r, HotspotProvisioner $prov)
  {
    $payload = $this->arr($r->all());
    $orderId = $payload['order_id'] ?? null;
    if (!$orderId) {
      Log::warning('Webhook tanpa order_id', ['body' => $payload]);
      return response()->json(['ok' => true]); // jangan 4xx ke Midtrans
    }

    // Siapkan nilai yang umum dipakai
    $incomingAppStatus = $this->normalizeIncoming($payload['transaction_status'] ?? '');
    $providerRef = $payload['transaction_id'] ?? null;

    DB::transaction(function () use ($orderId, $payload, $incomingAppStatus, $providerRef) {
      // lock row agar konsisten saat merge & set paid_at
      $p = Payment::where('order_id', $orderId)->lockForUpdate()->first();

      // raw & actions lama (jangan hilangkan)
      $prevRaw = $this->arr($p->raw ?? []);
      $prevActions = $this->arr($p->actions ?? []);

      // Notifikasi Midtrans biasanya TIDAK bawa actions → pertahankan yang lama
      $newRaw = $payload;
      if (empty($newRaw['actions']) && !empty($prevActions)) {
        $newRaw['actions'] = $prevActions;
      }

      // Merge raw lama + baru (yang baru overwrite field lama)
      $mergedRaw = array_replace_recursive($prevRaw, $newRaw);

      // Tentukan status akhir (pakai helper model kalau ada)
      if (method_exists(Payment::class, 'mergeStatus')) {
        $finalStatus = Payment::mergeStatus($p->status ?? null, $incomingAppStatus);
      } else {
        $finalStatus = $incomingAppStatus;
      }

      $clientId = OrderId::client($orderId) ?? 'DEFAULT';

      // Update/insert
      Payment::updateOrCreate(
        ['order_id' => $orderId],
        [
          'client_id'    => $clientId,
          'provider'     => 'midtrans',
          'provider_ref' => $providerRef ?: ($p->provider_ref ?? null),
          'status'       => $finalStatus,
          'raw'          => $mergedRaw,
          // simpan actions permanen jika ada di payload baru / hasil merge
          'actions'      => !empty($newRaw['actions']) ? $newRaw['actions'] : ($p->actions ?? null),
          // set paid_at sekali saat jadi PAID
          'paid_at'      => ($finalStatus === 'PAID') ? (($p->paid_at) ?: now()) : ($p->paid_at ?? null),
        ]
      );
    });

    // Post-commit: kalau sudah PAID, langsung provision (log Mikrotik stub)
    $p = Payment::where('order_id', $orderId)->first();
    if ($p && $p->status === 'PAID') {
      try {
        $clientId = \App\Support\OrderId::client($orderId) ?? 'DEFAULT';
        $u = $prov->provision($orderId, $clientId);
        if ($u) $prov->pushToMikrotik($u);
      } catch (\Throwable $e) {
        Log::error('Provision after webhook gagal', ['order_id' => $orderId, 'err' => $e->getMessage()]);
      }
    }

    return response()->json(['ok' => true]);
  }
}
