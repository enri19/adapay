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

    $order   = \App\Models\HotspotOrder::where('order_id',$orderId)->first();
    $client  = $order ? \App\Models\Client::where('client_id',$order->client_id)->first() : null;

    $authMode = $client ? $client->auth_mode : null;
    $portalUrl = $client ? $client->hotspot_portal : null;

    return view('payments.return', [
      'orderId'   => $orderId,
      'status'    => $p->status,
      'creds'     => $creds,
      'authMode'  => $authMode,
      'portalUrl' => $portalUrl
    ]);
  }
}
