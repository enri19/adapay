<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\HotspotOrder;
use App\Models\Client;
use App\Models\HotspotUser;
use App\Jobs\ProvisionHotspotIfPaid;
use App\Jobs\PaymentBecamePaid;
use App\Jobs\SyncMidtransStatus;
use App\Services\HotspotProvisioner;

class ReturnController extends Controller
{
  public function show(Request $r, HotspotProvisioner $prov)
  {
    $orderId = $r->query('order_id');
    if (!$orderId) {
      return view('payments.return', [
        'orderId'       => null,
        'status'        => 'MISSING',
        'creds'         => null,
        'authMode'      => null,
        'hotspotPortal' => null,
      ]);
    }

    $payment = Payment::where('order_id', $orderId)->first();

    // Jika payment belum ada, coba trigger job untuk cek / provision
    if (!$payment) {
      ProvisionHotspotIfPaid::dispatch($orderId)->onQueue('router');
      PaymentBecamePaid::dispatch($orderId)->onQueue('wa');
    }

    $status = $payment ? $payment->status : Payment::S_PENDING;
    $user   = HotspotUser::where('order_id', $orderId)->first();
    $creds  = $user ? ['u' => $user->username, 'p' => $user->password] : null;

    // Kalau belum paid, sinkronkan status provider di background
    try {
      if ($status !== Payment::S_PAID) {
        SyncMidtransStatus::dispatch($orderId)->afterResponse();
        // TODO: tambahkan SyncDanaStatus job kalau mau polling status DANA juga
      }
    } catch (\Throwable $e) {
      // abaikan error agar return tetap lancar
    }

    $order  = HotspotOrder::where('order_id', $orderId)->first();
    $client = $order ? Client::where('client_id', $order->client_id)->first() : null;

    return view('payments.return', [
      'orderId'       => $orderId,
      'status'        => $status,
      'creds'         => $creds,
      'authMode'      => $client ? $client->auth_mode : null,
      'hotspotPortal' => $client ? $client->hotspot_portal : null,
    ]);
  }
}
