<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Services\HotspotProvisioner;
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

    // kalau belum PAID, sinkron ulang status di background
    try {
      if ($status !== \App\Models\Payment::S_PAID) {
        \App\Jobs\SyncMidtransStatus::dispatch($orderId)->afterResponse();
      }
    } catch (\Throwable $e) { /* ignore */ }

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
