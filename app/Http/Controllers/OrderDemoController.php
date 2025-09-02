<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OrderDemoController extends Controller
{
  public function show(Request $r)
  {
    $host = strtolower($r->getHost());
    $client = 'DEFAULT';
    if ($host !== 'pay.adanih.info') {
      $parts = explode('.', $host);
      if (count($parts) > 3 && !empty($parts[0])) {
        $client = strtoupper(preg_replace('/[^A-Z0-9]/', '', $parts[0]));
      } else {
        $q = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $r->query('client')));
        if ($q) $client = $q;
      }
    } else {
      $q = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $r->query('client')));
      if ($q) $client = $q;
    }

    $orderId = 'DEMO-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

    // data awal untuk view
    $order = (object) [
      'order_id'       => $orderId,
      'client_id'      => $client,
      'status'         => 'PENDING',
      'amount'         => 5000,
      'currency'       => 'IDR',
      'payment_method' => 'QRIS (Demo)',
      'provider'       => 'demo',
      'provider_ref'   => 'DEMO-REF-' . strtoupper(substr(md5($orderId), 0, 8)),
      // kadaluarsa 5 menit dari sekarang (client-side countdown)
      'expires_in_sec' => 5 * 60,
      // contoh kredensial yang akan muncul saat PAID
      'hotspot_user'   => 'user_' . substr(md5($orderId), 0, 5),
      'hotspot_pass'   => strtoupper(substr(md5(strrev($orderId)), 0, 8)),
    ];

    return view('hotspot.order-demo', compact('order'));
  }
}
