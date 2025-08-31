<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Services\HotspotProvisioner;
use App\Jobs\SendWhatsAppMessage;
use App\Services\WhatsAppGateway;
use Illuminate\Support\Facades\Log;

class ReturnController extends Controller
{
  private function initMidtrans(): void {
    \Midtrans\Config::$serverKey    = config('midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', '');
    \Midtrans\Config::$isProduction = (bool) (config('midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false));
    if (property_exists(\Midtrans\Config::class, 'isSanitized')) \Midtrans\Config::$isSanitized = true;
  }

  private function getAuthModeForOrder(string $orderId): ?string
  {
    $order = \App\Models\HotspotOrder::where('order_id', $orderId)->first();
    if (!$order) return null;

    $client = \App\Models\Client::where('client_id', $order->client_id)->first();
    return $client ? $client->auth_mode : null; // 'code' atau 'userpass' (atau null)
  }

  private function waSendPaid(string $orderId): void
  {
    try {
      $order = \App\Models\HotspotOrder::where('order_id', $orderId)->first();
      if (!$order || !$order->buyer_phone) return;

      $user = \App\Models\HotspotUser::where('order_id', $orderId)->first();
      $to   = \App\Support\Phone::normalizePhone($order->buyer_phone);

      $authMode = $this->getAuthModeForOrder($orderId) ?: 'userpass';

      $msg = $user
        ? $this->buildWaPaidWithCredsMessage(
            $orderId,
            $user->username,
            $user->password,
            $user->profile,
            (int) $user->duration_minutes,
            $authMode
          )
        : $this->buildWaPaidSimpleMessage($orderId);

      // === ASYNC ===
      $conn = config('queue.default', 'sync');

      // 1) Pakai worker queue kalau ada (benar-benar non-blocking)
      if ($conn !== 'sync') {
        SendWhatsAppMessage::dispatch($to, $msg, $orderId)->onQueue('wa');
        Log::info('wa.paid.queue.dispatched', compact('orderId','to','conn'));
        return;
      }

      // 2) Fallback: kirim setelah response (tak butuh worker)
      try {
        SendWhatsAppMessage::dispatchAfterResponse($to, $msg, $orderId);
        Log::info('wa.paid.after_response.dispatched', compact('orderId','to'));
        return;
      } catch (\Throwable $e) {
        Log::warning('wa.paid.after_response.failed', ['order_id'=>$orderId, 'err'=>$e->getMessage()]);
      }

      // 3) Fallback terakhir: sinkron (biar nggak hilang sama sekali)
      app(WhatsAppGateway::class)->send($to, $msg);
      Log::info('wa.paid.sync.sent', compact('orderId','to'));
    } catch (\Throwable $e) {
      \Log::warning('paid.whatsapp_failed', ['order_id' => $orderId, 'err' => $e->getMessage()]);
    }
  }

  private function buildWaPaidSimpleMessage(string $orderId): string
  {
    $orderUrl = url("/hotspot/order/{$orderId}");
    return implode("\n", [
      "*Pembayaran Berhasil ✅*",
      "Order ID : {$orderId}",
      "",
      "Akun kamu sedang disiapkan.",
      "Pantau di: {$orderUrl}",
      "_Terima kasih_ 🙏",
    ]);
  }

  private function buildWaPaidWithCredsMessage(
    string $orderId,
    string $username,
    string $password,
    ?string $profile,
    int $duration,
    string $authMode = 'userpass'
  ): string
  {
    $orderUrl = url("/hotspot/order/{$orderId}");
    $dur = $duration ? "{$duration} menit" : '-';

    $header = [
      "*Pembayaran Berhasil ✅*",
      "Order ID : {$orderId}",
      "Profile  : {$profile} ({$dur})",
      ""
    ];

    $isCode = ($authMode === 'code') || ($username === $password);

    $creds = $isCode
      ? [
          "*Kode Hotspot Kamu*",
          "Kode : `{$username}`",
          "",
          "Gunakan kode tersebut sebagai *Username* dan *Password* saat login."
        ]
      : [
          "*Akun Hotspot Kamu*",
          "Username : `{$username}`",
          "Password : `{$password}`"
        ];

    $footer = [
      "",
      "Kamu bisa login sekarang.",
      "Cek kembali di: {$orderUrl}",
      "_Terima kasih_ 🙏",
    ];

    return implode("\n", array_merge($header, $creds, $footer));
  }

  public function show(Request $r, HotspotProvisioner $prov)
  {
    $orderId = $r->query('order_id');
    if (!$orderId) {
      return view('payments.return', ['orderId'=>null,'status'=>'MISSING','creds'=>null]);
    }

    $p = \App\Models\Payment::where('order_id', $orderId)->first();
    if (!$p) {
      \App\Jobs\ProvisionHotspotIfPaid::dispatch($orderId)->onQueue('router');
      \App\Jobs\PaymentBecamePaid::dispatch($orderId)->onQueue('wa');
    }

    // --- DB-only, cepat ---
    $status = $p->status;
    $u = \App\Models\HotspotUser::where('order_id', $orderId)->first();
    $creds = $u ? ['u'=>$u->username, 'p'=>$u->password] : null;

    // --- Trigger async setelah response ---
    try {
      if ($status !== \App\Models\Payment::S_PAID) {
        \App\Jobs\SyncMidtransStatus::dispatch($orderId)->afterResponse();
      } else {
        if (!$u) \App\Jobs\ProvisionHotspotIfPaid::dispatch($orderId)->onQueue('router')->afterResponse();
        // WA: cukup biarkan PaymentBecamePaid yang handle ketika status jadi PAID
      }
    } catch (\Throwable $e) {
      \Log::debug('return.async.enqueue_failed', ['order_id'=>$orderId, 'err'=>$e->getMessage()]);
    }

    // --- render cepat; smart loader akan poll JSON & auto refresh bila perlu ---
    $order   = \App\Models\HotspotOrder::where('order_id',$orderId)->first();
    $client  = $order ? \App\Models\Client::where('client_id',$order->client_id)->first() : null;

    return view('payments.return', [
      'orderId'       => $orderId,
      'status'        => $status,
      'creds'         => $creds,
      'authMode'      => $client ? $client->auth_mode : null,
      'hotspotPortal' => $client ? $client->hotspot_portal : null,
    ]);
  }
}
