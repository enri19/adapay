<?php
namespace App\Http\Controllers;

use App\Models\HotspotVoucher;
use App\Models\HotspotOrder;
use App\Models\HotspotUser;
use App\Payments\Payment as PaymentResolver;
use App\Services\HotspotProvisioner;
use App\Support\OrderId;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HotspotController extends Controller
{
  public function index(\Illuminate\Http\Request $request)
  {
    $clientId = \App\Support\ClientResolver::resolve($request);

    $vouchers = \App\Models\HotspotVoucher::forClient($clientId)
      ->where('is_active', true)
      ->orderBy('price')
      ->get();

    return view('hotspot.index', [
      'vouchers' => $vouchers,
      'resolvedClientId' => $clientId, // supaya hidden input di Blade terisi
    ]);
  }

  public function orderView(string $orderId)
  {
    return view('hotspot.order', compact('orderId'));
  }

  public function checkout(Request $request)
  {
    $data = $request->validate([
      'voucher_id' => 'required|exists:hotspot_vouchers,id',
      'name' => 'nullable|string|max:100',
      'email' => 'nullable|email',
      'phone' => 'nullable|string|max:30',
      'method' => 'nullable|string|in:qris,gopay,shopeepay',
      'client_id' => 'nullable|string|max:32',
    ]);

    // SELALU resolve dari host/URL (abaikan session)
    $clientId = \App\Support\ClientResolver::resolve($request);

    // pastikan voucher milik client ini (atau global)
    $voucher = \App\Models\HotspotVoucher::query()
      ->where('id', (int)$data['voucher_id'])
      ->forClient($clientId)
      ->where('is_active', true)
      ->first();

    if (!$voucher) {
      return response()->json([
        'error' => 'INVALID_VOUCHER',
        'message' => 'Voucher tidak tersedia untuk lokasi ini.'
      ], 422);
    }

    $orderId = OrderId::make($clientId);

    $voucher = \App\Models\HotspotVoucher::findOrFail($data['voucher_id']);
    $adapter = \App\Payments\Payment::provider();

    try {
      if (($data['method'] ?? 'qris') === 'qris') {
        $resp = $adapter->createQris($orderId, (int)$voucher->price, [
          'name'  => $data['name'] ?? null,
          'email' => $data['email'] ?? null,
          'phone' => $data['phone'] ?? null,
        ], ['expiry_minutes' => 30]);
      } else {
        $resp = $adapter->createEwallet($data['method'], $orderId, (int)$voucher->price, [
          'name'  => $data['name'] ?? null,
          'email' => $data['email'] ?? null,
          'phone' => $data['phone'] ?? null,
        ], ['callback_url' => url('/payments/return')]);
      }

      \App\Models\HotspotOrder::updateOrCreate(
        ['order_id' => $orderId],
        [
          'client_id' => $clientId,
          'hotspot_voucher_id' => $voucher->id,
          'buyer_name'  => $data['name'] ?? null,
          'buyer_email' => $data['email'] ?? null,
          'buyer_phone' => $data['phone'] ?? null,
        ]
      );

      \App\Models\Payment::updateOrCreate(
        ['order_id' => $orderId],
        [
          'client_id'    => $clientId,
          'provider'     => 'midtrans',
          'provider_ref' => $resp['provider_ref'] ?? null,
          'amount'       => (int)$voucher->price,
          'currency'     => 'IDR',
          'status'       => $resp['status'] ?? 'PENDING',
          'qr_string'    => $resp['qr_string'] ?? null,
          'raw'          => $resp,
          'actions'      => $resp['actions'] ?? null,
        ]
      );

      return response()->json(['order_id' => $orderId, 'midtrans' => $resp], 201);

    } catch (\Throwable $e) {
      $msg = $e->getMessage();
      $code = 'CHECKOUT_FAILED'; $http = 502;
      if (strpos($msg, 'CHANNEL_INACTIVE') !== false || strpos($msg, '"status_code":"402"') !== false) { $code='CHANNEL_INACTIVE'; }
      if (stripos($msg, 'pop id') !== false) { $code='POP_REQUIRED'; }
      if (stripos($msg, 'UPSTREAM_TEMPORARY') !== false || strpos($msg, '"status_code":"500"') !== false) { $code='UPSTREAM_TEMPORARY'; $http=503; }
      \Log::error('hotspot.checkout failed', ['order_id' => $orderId, 'err' => $msg]);
      return response()->json(['error' => $code, 'message' => $msg], $http);
    }
  }

  public function credentials(string $orderId, HotspotProvisioner $prov)
  {
    $user = $prov->provision($orderId) ?: HotspotUser::where('order_id', $orderId)->first();
    if (!$user) return response()->json(['ready' => false]);
    return response()->json([
      'ready' => true,
      'username' => $user->username,
      'password' => $user->password,
      'profile' => $user->profile,
      'duration_minutes' => $user->duration_minutes,
    ]);
  }
}
