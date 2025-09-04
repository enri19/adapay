<?php

namespace App\Http\Controllers;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use App\Models\Payment;
use App\Payments\Payment as PaymentResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
  private function initMidtrans(): void
  {
    \Midtrans\Config::$serverKey    = config('midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', '');
    \Midtrans\Config::$isProduction = (bool) (config('midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false));
    if (property_exists(\Midtrans\Config::class, 'isSanitized')) {
      \Midtrans\Config::$isSanitized = true;
    }
  }

  private function arr($x): array {
    if (is_array($x)) return $x;
    if (is_string($x)) { $d=json_decode($x,true); if (json_last_error()===JSON_ERROR_NONE) return $d?:[]; }
    return json_decode(json_encode($x), true) ?: [];
  }

  public function createQris(Request $request)
  {
    $data = $request->validate([
      'amount' => 'required|integer|min:1000',
      'name'   => 'nullable|string|max:100',
      'email'  => 'nullable|email',
      'phone'  => 'nullable|string|max:30',
    ]);

    $orderId = 'ORD-'.now()->format('Ymd-His').'-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6));
    $adapter = \App\Payments\Payment::provider();

    try {
      $resp = $adapter->createQris($orderId, (int)$data['amount'], [
        'name'  => $data['name'] ?? null,
        'email' => $data['email'] ?? null,
        'phone' => $data['phone'] ?? null,
      ], ['expiry_minutes' => 30]);

      $paymentData = [
        'provider'     => 'midtrans',
        'provider_ref' => $resp['provider_ref'] ?? null,
        'amount'       => (int)$data['amount'],
        'currency'     => 'IDR',
        'status'       => $resp['status'] ?? 'PENDING',
        'qr_string'    => $resp['qr_string'] ?? null,
        'raw'          => $resp,
      ];

      \App\Models\Payment::updateOrCreate(['order_id' => $orderId], $paymentData);

      return response()->json([
        'order_id'  => $orderId,
        'status'    => $resp['status'],
        'qr_string' => $resp['qr_string'] ?? null,
      ], 201);

    } catch (\Throwable $e) {
      \Log::error('createQris failed', ['order_id' => $orderId, 'err' => $e->getMessage()]);
      return response()->json(['error'=>'PAYMENT_CREATE_FAILED','message'=>$e->getMessage()], 502);
    }
  }

  public function show(string $orderId)
  {
    $p = \App\Models\Payment::where('order_id', $orderId)->first();
    if (!$p) {
      return response()->json(['error' => 'Not found'], 404, [
        'Cache-Control' => 'no-store',
        'Content-Type'  => 'application/json; charset=utf-8',
      ]);
    }

    $raw = is_array($p->raw) ? $p->raw : json_decode(json_encode($p->raw), true);
    $kind = 'unknown';
    if (!empty($p->qr_string) || (($raw['payment_type'] ?? '') === 'qris')) {
      $kind = 'qris';
    } elseif (!empty($p->actions) || in_array(($raw['payment_type'] ?? ''), ['gopay','shopeepay'], true)) {
      $kind = 'ewallet';
    }

    $data = $p->toArray();
    $data['kind'] = $kind;

    return response()->json(
      $data,
      200,
      ['Cache-Control' => 'no-store', 'Content-Type' => 'application/json; charset=utf-8'],
      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
  }

  public function createGopay(Request $request)
  {
    $data = $request->validate([
      'amount' => 'required|integer|min:1000',
      'name'   => 'nullable|string|max:100',
      'email'  => 'nullable|email',
      'phone'  => 'nullable|string|max:30',
    ]);

    $orderId = 'ORD-'.now()->format('Ymd-His').'-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6));
    $adapter = \App\Payments\Payment::provider();

    try {
      $resp = $adapter->createEwallet('gopay', $orderId, (int)$data['amount'], [
        'name'  => $data['name'] ?? null,
        'email' => $data['email'] ?? null,
        'phone' => $data['phone'] ?? null,
      ], ['callback_url' => url('/payments/return').'?order_id='.$orderId]);

      $paymentData = [
        'provider'     => 'midtrans',
        'provider_ref' => $resp['provider_ref'] ?? null,
        'amount'       => (int)$data['amount'],
        'currency'     => 'IDR',
        'status'       => $resp['status'] ?? 'PENDING',
        'qr_string'    => null,
        'raw'          => $resp,
      ];

      \App\Models\Payment::updateOrCreate(['order_id' => $orderId], $paymentData);

      return response()->json([
        'order_id' => $orderId,
        'status'   => $resp['status'],
        'actions'  => $resp['actions'] ?? null,
      ], 201);

    } catch (\Throwable $e) {
      \Log::error('createGopay failed', ['order_id' => $orderId, 'err' => $e->getMessage()]);
      return response()->json(['error'=>'PAYMENT_CREATE_FAILED','message'=>$e->getMessage()], 502);
    }
  }

  private function pickQrUrl(array $raw): ?string {
    // adapter map { actions: { qr_code_url: ... } }
    if (isset($raw['actions']['qr_code_url'])) return $raw['actions']['qr_code_url'];
    // midtrans list [{name,url},...]
    foreach (($raw['actions'] ?? []) as $a) {
      $name = strtolower($a['name'] ?? '');
      if (in_array($name, ['generate-qr-code','generate-qr-code-v2','qr_checkout'], true))
        return $a['url'] ?? null;
    }
    return null;
  }

  public function ewalletQr(string $orderId) {
    $p = \App\Models\Payment::where('order_id', $orderId)->first();
    if (!$p) return response('Not found', 404);

    $raw = $this->arr($p->raw);
    $qrUrl = $this->pickQrUrl($raw);

    // fallback: refresh status sekali kalau belum ada actions
    if (!$qrUrl) {
      $paymentType = strtolower($raw['payment_type'] ?? '');
      $txId = $raw['transaction_id'] ?? null;
      if ($paymentType === 'gopay' && $txId) {
        $base = (bool)(config('midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false))
          ? 'https://api.midtrans.com'
          : 'https://api.sandbox.midtrans.com';
        $qrUrl = $base.'/v2/gopay/'.$txId.'/qr-code';
      }
    }

    if (!$qrUrl) return response('QR not available', 404);

    $serverKey = config('midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', '');
    $resp = \Illuminate\Support\Facades\Http::withBasicAuth($serverKey, '')
      ->withHeaders(['Accept'=>'image/png,image/*;q=0.8,*/*;q=0.5'])
      ->withOptions(['allow_redirects'=>true,'timeout'=>20])
      ->get($qrUrl);

    if (!$resp->ok()) return response('Upstream error', 502);

    return response($resp->body(), 200)
      ->header('Content-Type', $resp->header('Content-Type', 'image/png'))
      ->header('Cache-Control', 'no-store, max-age=0');
  }

  public function qrisPng(string $orderId)
  {
    $p = \App\Models\Payment::where('order_id', $orderId)->first();

    $qr = null;
    if ($p) {
      // prioritas kolom, fallback ke raw['qr_string']
      $qr = $p->qr_string;
      if (!$qr && is_array($p->raw)) {
        $qr = $p->raw['qr_string'] ?? null;
      }
    }
    if (!$qr) return response('QR not available', 404);

    $png = \Endroid\QrCode\Builder\Builder::create()
      ->writer(new \Endroid\QrCode\Writer\PngWriter())
      ->data($qr)
      ->encoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
      ->size(256)
      ->margin(1)
      ->build()
      ->getString();

    return response($png, 200)
      ->header('Content-Type', 'image/png')
      ->header('Cache-Control', 'no-store, max-age=0');
  }

  public function createSnap(Request $r)
  {
    $data = $r->validate([
      'amount' => 'required|integer|min:1000',
      'name'   => 'nullable|string|max:100',
      'email'  => 'nullable|email',
      'phone'  => 'nullable|string|max:30',
    ]);

    $this->initMidtrans();

    $orderId = 'ORD-'.now()->format('Ymd-His').'-'.\Str::upper(\Str::random(6));

    // simpan dulu PENDING di DB kamu
    \App\Models\Payment::updateOrCreate(
      ['order_id' => $orderId],
      [
        'provider' => 'midtrans',
        'amount'   => (int)$data['amount'],
        'currency' => 'IDR',
        'status'   => \App\Models\Payment::S_PENDING ?? 'PENDING',
        'raw'      => [],
      ]
    );

    $payload = [
      'transaction_details' => [
        'order_id'     => $orderId,
        'gross_amount' => (int)$data['amount'],
      ],
      'customer_details' => [
        'first_name' => $data['name']  ?? null,
        'email'      => $data['email'] ?? null,
        'phone'      => $data['phone'] ?? null,
      ],
      // Opsional: batasi metode yang tampil.
      // Kalau dikosongkan, Snap akan otomatis tampilkan yang aktif.
      // 'enabled_payments' => ['gopay','shopeepay','bni_va','bri_va','permata_va','echannel'],

      // Callback (opsional): halaman “finish” kamu
      'callbacks' => [
        'finish' => url('/payments/finish?order_id='.$orderId),
      ],
      // Expiry (opsional)
      'expiry' => [
        'unit'     => 'minutes',
        'duration' => 30,
      ],
    ];

    $snap = \Midtrans\Snap::createTransaction($payload);
    // $snap adalah array: ['token' => '...','redirect_url' => '...']

    // simpan raw minimal
    \App\Models\Payment::where('order_id', $orderId)->update(['raw' => $snap]);

    return response()->json([
      'order_id'     => $orderId,
      'snap_token'   => $snap['token'] ?? null,
      'redirect_url' => $snap['redirect_url'] ?? null,
    ], 201);
  }
}
