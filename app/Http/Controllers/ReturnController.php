<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Services\HotspotProvisioner;

class ReturnController extends Controller
{
  private function initMidtrans(): void {
    \Midtrans\Config::$serverKey    = config('midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', '');
    \Midtrans\Config::$isProduction = (bool) (config('midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false));
    if (property_exists(\Midtrans\Config::class, 'isSanitized')) \Midtrans\Config::$isSanitized = true;
  }

  private function waSendPaid(string $orderId): void
  {
    try {
      $order = \App\Models\HotspotOrder::where('order_id', $orderId)->first();
      if (!$order || !$order->buyer_phone) return;

      $user = \App\Models\HotspotUser::where('order_id', $orderId)->first();
      $to   = \App\Support\Phone::normalizePhone($order->buyer_phone);

      $msg = $user
        ? $this->buildWaPaidWithCredsMessage($orderId, $user->username, $user->password, $user->profile, (int) $user->duration_minutes)
        : $this->buildWaPaidSimpleMessage($orderId);

      app(\App\Services\WhatsAppGateway::class)->send($to, $msg);
      \Log::info('return.paid.whatsapp', compact('orderId', 'to'));
    } catch (\Throwable $e) {
      \Log::warning('return.paid.whatsapp_failed', ['order_id' => $orderId, 'err' => $e->getMessage()]);
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

  private function buildWaPaidWithCredsMessage(string $orderId, string $username, string $password, ?string $profile, int $duration): string
  {
    $orderUrl = url("/hotspot/order/{$orderId}");
    $dur = $duration ? "{$duration} menit" : '-';

    return implode("\n", [
      "*Pembayaran Berhasil ✅*",
      "Order ID : {$orderId}",
      "Profile  : {$profile} ({$dur})",
      "",
      "*Akun Hotspot Kamu*",
      "Username : `{$username}`",
      "Password : `{$password}`",
      "",
      "Kamu bisa login sekarang.",
      "Cek kembali di: {$orderUrl}",
      "_Terima kasih_ 🙏",
    ]);
  }

  public function show(Request $r, HotspotProvisioner $prov)
  {
    $orderId = $r->query('order_id');
    if (!$orderId) return view('payments.return', ['orderId'=>null,'status'=>'MISSING','creds'=>null]);

    $p = Payment::where('order_id', $orderId)->first();
    if (!$p) return view('payments.return', ['orderId'=>$orderId,'status'=>'UNKNOWN','creds'=>null]);

    // Pastikan status terbaru (fallback jika webhook belum sampai)
    $this->initMidtrans();
    try {
      $latest = \Midtrans\Transaction::status($orderId);
      $arr = is_array($latest) ? $latest : json_decode(json_encode($latest), true);
      $rawStatus = strtolower($arr['transaction_status'] ?? 'pending');
      $incoming = app(\App\Payments\Providers\MidtransAdapter::class)->normalizeStatus($rawStatus);

      $p->status = \App\Models\Payment::mergeStatus($p->status, $incoming);
      $p->raw = array_merge(is_array($p->raw)?$p->raw:[], $arr);
      if (in_array($rawStatus, ['capture','settlement','success'], true) && !$p->paid_at) $p->paid_at = now();
      $p->save();
    } catch (\Throwable $e) { /* ignore; gunakan status DB */ }

    $creds = null;
    if ($p->status === \App\Models\Payment::S_PAID) {
      // Provision di sini juga (fallback) agar user langsung dapat akun
      $u = $prov->provision($orderId);
      if ($u) { $prov->pushToMikrotik($u); $creds = ['u'=>$u->username,'p'=>$u->password]; }
    }

    // [ADD] Jika terjadi transisi ke PAID, kirim WA di sini
    if ($p->status === \App\Models\Payment::S_PAID && $prevStatus !== \App\Models\Payment::S_PAID) {
      $this->waSendPaid($orderId);
    }

    $order   = \App\Models\HotspotOrder::where('order_id',$orderId)->first();
    $client  = $order ? \App\Models\Client::where('client_id',$order->client_id)->first() : null;

    $authMode = $client ? $client->auth_mode : null;
    $hotspotPortal = $client ? $client->hotspot_portal : null;

    return view('payments.return', [
      'orderId'   => $orderId,
      'status'    => $p->status,
      'creds'     => $creds,
      'authMode'  => $authMode,
      'hotspotPortal' => $hotspotPortal,
    ]);
  }
}
