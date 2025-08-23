<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Payments\Payment as PaymentResolver;
use App\Models\Payment;

class EmoneyController extends Controller
{
  public function charge(Request $request)
  {
    $data = $request->validate([
      'channel' => 'required|string|in:gopay,shopeepay',
      'amount'  => 'required|integer|min:1000',
      'name'    => 'nullable|string|max:100',
      'email'   => 'nullable|email',
      'phone'   => 'nullable|string|max:30',
    ]);

    $clientId = \App\Support\OrderId::sanitizeClient($data['client_id'] ?? 'DEFAULT');
    $orderId  = \App\Support\OrderId::make($clientId);
    $adapter = \App\Payments\Payment::provider();

    try {
      $resp = $adapter->createEwallet(
        $data['channel'],
        $orderId,
        (int)$data['amount'],
        [
          'name'  => $data['name'] ?? null,
          'email' => $data['email'] ?? null,
          'phone' => $data['phone'] ?? null,
        ],
        ['callback_url' => url('/payments/return').'?order_id='.$orderId]
      );

      $paymentData = [
        'client_id'    => $clientId,
        'provider'     => 'midtrans',
        'provider_ref' => $resp['provider_ref'] ?? null,
        'amount'       => (int)$data['amount'],
        'currency'     => 'IDR',
        'status'       => $resp['status'] ?? 'PENDING',
        'qr_string'    => null,
        'raw'          => $resp,
      ];

      \App\Models\Payment::updateOrCreate(['order_id' => $resp['order_id']], $paymentData);

      return response()->json([
        'order_id' => $resp['order_id'],
        'status'   => $resp['status'],
        'actions'  => $resp['actions'] ?? null,
      ], 201);

    } catch (\Throwable $e) {
      $msg = $e->getMessage();
      $code = 'PAYMENT_CREATE_FAILED'; $http = 502;
      if (strpos($msg, 'UPSTREAM_TEMPORARY') !== false || strpos($msg, '"status_code":"500"') !== false) { $code='UPSTREAM_TEMPORARY'; $http=503; }
      elseif (strpos($msg, 'CHANNEL_INACTIVE') !== false || strpos($msg, '"status_code":"402"') !== false) { $code='CHANNEL_INACTIVE'; }
      elseif (stripos($msg, 'pop id') !== false) { $code='POP_REQUIRED'; }
      \Log::error('emoney.charge failed', ['order_id' => $orderId, 'err' => $msg]);
      return response()->json(['error' => $code, 'message' => $msg], $http);
    }
  }
}
