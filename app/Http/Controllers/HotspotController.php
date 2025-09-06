<?php
namespace App\Http\Controllers;

use App\Models\HotspotVoucher;
use App\Models\HotspotOrder;
use App\Models\HotspotUser;
use App\Payments\Payment as PaymentResolver;
use App\Services\HotspotProvisioner;
use App\Support\OrderId;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class HotspotController extends Controller
{
  public function index(Request $request)
  {
    // … di dalam index()
    $host = strtolower($request->getHost());
    $isBaseHost = ($host === 'pay.adanih.info');

    $clientId = \App\Support\ClientResolver::resolve($request);

    $clients = collect();
    if ($isBaseHost) {
      $clients = DB::table('clients')->where('is_active',1)
                  ->orderBy('name')->get(['client_id','name','slug']);

      $q = trim((string) $request->query('client',''));
      if ($q !== '') {
        $match = $clients->first(fn($c)=> strcasecmp($c->client_id,$q)===0 || strcasecmp((string)$c->slug,$q)===0);
        if ($match) $clientId = $match->client_id;
      }
    }

    // kalau base host & belum ada pilihan → biarkan null
    $selectedClientId = ($isBaseHost && ($clientId === 'DEFAULT' || empty($clientId))) ? null : $clientId;

    // voucher hanya di-load jika sudah ada client terpilih
    $vouchers = $selectedClientId ? \App\Models\HotspotVoucher::listForPortal($selectedClientId) : collect();

    return view('hotspot.snap', [
      'vouchers'         => $vouchers,
      'resolvedClientId' => $selectedClientId, // bisa null
      'isBaseHost'       => $isBaseHost,
      'clients'          => $clients,
      'layoutHeader'     => 'minimal', // 'full' | 'minimal' | 'none'
    ]);
  }

  public function dana(Request $request)
  {
    // … di dalam index()
    $host = strtolower($request->getHost());
    $isBaseHost = ($host === 'pay.adanih.info');

    $clientId = \App\Support\ClientResolver::resolve($request);

    $clients = collect();
    if ($isBaseHost) {
      $clients = DB::table('clients')->where('is_active',1)
                  ->orderBy('name')->get(['client_id','name','slug']);

      $q = trim((string) $request->query('client',''));
      if ($q !== '') {
        $match = $clients->first(fn($c)=> strcasecmp($c->client_id,$q)===0 || strcasecmp((string)$c->slug,$q)===0);
        if ($match) $clientId = $match->client_id;
      }
    }

    // kalau base host & belum ada pilihan → biarkan null
    $selectedClientId = ($isBaseHost && ($clientId === 'DEFAULT' || empty($clientId))) ? null : $clientId;

    // voucher hanya di-load jika sudah ada client terpilih
    $vouchers = $selectedClientId ? \App\Models\HotspotVoucher::listForPortal($selectedClientId) : collect();

    return view('hotspot.dana', [
      'vouchers'         => $vouchers,
      'resolvedClientId' => $selectedClientId, // bisa null
      'isBaseHost'       => $isBaseHost,
      'clients'          => $clients,
      'layoutHeader'     => 'minimal', // 'full' | 'minimal' | 'none'
    ]);
  }

  public function apiVouchers(Request $request)
  {
    $q = trim((string) $request->query('client', ''));
    $clientId = 'DEFAULT';

    if ($q !== '') {
      $row = DB::table('clients')
        ->where('is_active', 1)
        ->where(function($w) use ($q) {
          $w->where('client_id', $q)->orWhere('slug', $q);
        })
        ->select('client_id')
        ->first();
      $clientId = $row ? $row->client_id : strtoupper(preg_replace('/[^A-Z0-9]/', '', $q));
    }

    $vouchers = \App\Models\HotspotVoucher::listForPortal($clientId);

    $data = $vouchers->map(function($v){
      return [
        'id'    => (int) $v->id,
        'name'  => $v->name,
        'price' => (int) $v->price,
      ];
    })->values();

    return response()->json([
      'ok'        => true,
      'client_id' => $clientId,
      'count'     => $data->count(),
      'data'      => $data,
    ]);
  }

  public function orderView(string $orderId)
  {
    return view('hotspot.order-snap', [
      'orderId'      => $orderId,
      'layoutHeader' => 'minimal',
    ]);
  }

  public function checkout(Request $request)
  {
    $data = $request->validate([
      'voucher_id' => 'required|exists:hotspot_vouchers,id',
      'name'       => 'nullable|string|max:100',
      'email'      => 'nullable|email',
      'phone'      => 'nullable|string|max:30',
      'method'     => 'nullable|string|in:qris,gopay,shopeepay,dana',
      'client_id'  => 'nullable|string|max:32',
    ]);

    // --- RESOLVE CLIENT SECARA TEGAS ---
    $host       = strtolower($request->getHost());
    $isBaseHost = ($host === 'pay.adanih.info');

    // Resolve client from payload or fallback to resolver
    $clientFromPayload = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($data['client_id'] ?? '')));
    $clientId = null;
    if ($clientFromPayload !== '') {
      $row = DB::table('clients')
        ->where('is_active', 1)
        ->where(function($w) use ($clientFromPayload) {
          $w->where('client_id', $clientFromPayload)
            ->orWhere('slug', $clientFromPayload);
        })
        ->select('client_id')
        ->first();
      if ($row) {
        $clientId = $row->client_id; // valid dari payload
      }
    }

    if (!$clientId) {
      $clientId = \App\Support\ClientResolver::resolve($request);
    }

    // --- Validasi voucher milik client ---
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
    
    // Tentukan provider pembayaran berdasarkan method
    $method = $data['method'] ?? 'qris';  // Default 'qris' jika tidak ada method
    $adapter = \App\Payments\Payment::provider();

    // Logic provider berdasarkan method pembayaran yang dipilih
    $provider = 'midtrans';  // Default provider
    $resp = [];

    try {
      if ($method === 'qris') {
        $resp = $adapter->createQris($orderId, (int)$voucher->price, [
          'name'  => $data['name'] ?? null,
          'email' => $data['email'] ?? null,
          'phone' => $data['phone'] ?? null,
        ], ['expiry_minutes' => 30]);
      } elseif ($method === 'dana') {
        $provider = 'dana';  // Jika menggunakan DANA
        $resp = $adapter->createEwallet('dana', $orderId, (int)$voucher->price, [
          'name'  => $data['name'] ?? null,
          'email' => $data['email'] ?? null,
          'phone' => $data['phone'] ?? null,
        ], ['callback_url' => url('/payments/return')]);
      } else {
        $resp = $adapter->createEwallet($method, $orderId, (int)$voucher->price, [
          'name'  => $data['name'] ?? null,
          'email' => $data['email'] ?? null,
          'phone' => $data['phone'] ?? null,
        ], ['callback_url' => url('/payments/return')]);
      }

      // Simpan Hotspot Order
      \App\Models\HotspotOrder::updateOrCreate(
        ['order_id' => $orderId],
        [
          'client_id'           => $clientId,
          'hotspot_voucher_id'  => $voucher->id,
          'buyer_name'          => $data['name'] ?? null,
          'buyer_email'         => $data['email'] ?? null,
          'buyer_phone'         => $data['phone'] ?? null,
        ]
      );

      // Simpan Payment
      \App\Models\Payment::updateOrCreate(
        ['order_id' => $orderId],
        [
          'client_id'    => $clientId,
          'provider'     => $provider,  // Dynamically set the provider here
          'provider_ref' => $resp['provider_ref'] ?? null,
          'amount'       => (int)$voucher->price,
          'currency'     => 'IDR',
          'status'       => $resp['status'] ?? 'PENDING',
          'qr_string'    => $resp['qr_string'] ?? null,
          'raw'          => $resp,
          'actions'      => $resp['actions'] ?? null,
        ]
      );

      // Kirim invoice via WhatsApp
      $this->waSendInvoice($data, $orderId, $voucher, $resp);

      return response()->json(['order_id' => $orderId, 'payment' => $resp], 201);

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

  // public function checkoutSnap(Request $r)
  // {
  //   // Inisialisasi Midtrans
  //   \Midtrans\Config::$serverKey    = config('midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', '');
  //   \Midtrans\Config::$isProduction = (bool) (config('midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false));
  //   if (property_exists(\Midtrans\Config::class, 'isSanitized')) \Midtrans\Config::$isSanitized = true;

  //   $data = $r->validate([
  //     'amount'     => 'required|integer|min:1000',
  //     'name'       => 'nullable|string|max:100',
  //     'email'      => 'nullable|email',
  //     'phone'      => 'nullable|string|max:30',
  //     'voucher_id' => 'required|integer',
  //     'client_id'  => 'nullable|string|max:50',
  //     // 'enabled_payments' => 'array', // opsional
  //   ]);

  //   // --- RESOLVE CLIENT SECARA TEGAS ---
  //   $host       = strtolower($r->getHost());
  //   $isBaseHost = ($host === 'pay.adanih.info');

  //   // Resolve client from payload or fallback to resolver
  //   $clientFromPayload = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($data['client_id'] ?? '')));
  //   $clientId = null;
  //   if ($clientFromPayload !== '') {
  //     $row = DB::table('clients')
  //       ->where('is_active', 1)
  //       ->where(function($w) use ($clientFromPayload) {
  //         $w->where('client_id', $clientFromPayload)
  //           ->orWhere('slug', $clientFromPayload);
  //       })
  //       ->select('client_id')
  //       ->first();
  //     if ($row) {
  //       $clientId = $row->client_id; // valid dari payload
  //     }
  //   }

  //   if (!$clientId) {
  //     $clientId = \App\Support\ClientResolver::resolve($r);
  //   }

  //   // --- Validasi voucher milik client ---
  //   $voucher = \App\Models\HotspotVoucher::query()
  //     ->where('id', (int)$data['voucher_id'])
  //     ->forClient($clientId)
  //     ->where('is_active', true)
  //     ->first();

  //   if (!$voucher) {
  //     return response()->json([
  //       'error' => 'INVALID_VOUCHER',
  //       'message' => 'Voucher tidak tersedia untuk lokasi ini.'
  //     ], 422);
  //   }

  //   $orderId = OrderId::make($clientId);

  //   $voucher = \App\Models\HotspotVoucher::findOrFail($data['voucher_id']);

  //   $payload = [
  //     'transaction_details' => [
  //       'order_id'     => $orderId,
  //       'gross_amount' => (int) $data['amount'],
  //     ],
  //     'customer_details' => [
  //       'first_name' => $data['name']  ?? null,
  //       'email'      => $data['email'] ?? null,
  //       'phone'      => $data['phone'] ?? null,
  //     ],
  //     // Kalau mau membatasi channel, kirim dari FE lalu forward ke sini
  //     // 'enabled_payments' => $r->input('enabled_payments', []),

  //     'callbacks' => [
  //       'finish' => url('/hotspot/order/'.$orderId),
  //     ],
  //     'expiry' => [
  //       'unit'     => 'minutes',
  //       'duration' => 30,
  //     ],
  //   ];

  //   try {
  //     // Bisa balikan stdClass -> ubah ke array agar aman dipakai
  //     $respObj = \Midtrans\Snap::createTransaction($payload);
  //     $snap    = is_array($respObj) ? $respObj : json_decode(json_encode($respObj), true);

  //     // Fallback kalau SDK yang dipakai tidak balikan redirect_url/token
  //     if (empty($snap['token'])) {
  //       // sebagian versi SDK menyediakan helper ini
  //       if (method_exists(\Midtrans\Snap::class, 'createTransactionToken')) {
  //         $snap['token'] = \Midtrans\Snap::createTransactionToken($payload);
  //       } elseif (method_exists(\Midtrans\Snap::class, 'getSnapToken')) {
  //         $snap['token'] = \Midtrans\Snap::getSnapToken($payload);
  //       }
  //     }

  //     // Simpan Hotspot Order
  //     \App\Models\HotspotOrder::updateOrCreate(
  //       ['order_id' => $orderId],
  //       [
  //         'client_id'           => $clientId,
  //         'hotspot_voucher_id'  => $voucher->id,
  //         'buyer_name'          => $data['name'] ?? null,
  //         'buyer_email'         => $data['email'] ?? null,
  //         'buyer_phone'         => $data['phone'] ?? null,
  //       ]
  //     );

  //     // Simpan Payment
  //     \App\Models\Payment::updateOrCreate(
  //       ['order_id' => $orderId],
  //       [
  //         'client_id'    => $clientId,
  //         'provider'     => 'midtrans',
  //         'amount'       => (int)$voucher->price,
  //         'currency'     => 'IDR',
  //         'status'       => $snap['status'] ?? 'PENDING',
  //         'qr_string'    => $snap['qr_string'] ?? null,
  //         'raw'          => $snap,
  //         'actions'      => $snap['actions'] ?? null,
  //       ]
  //     );

  //     // Kirim invoice via WhatsApp
  //     // if (!empty($data['phone'])) {
  //     //   $cacheKey = "wa:inv:{$orderId}:{$data['phone']}";

  //     //   if (Cache::add($cacheKey, 1, now()->addHours(6))) {
  //     //     // Kunci baru dibuat -> kirim WA
  //     //     $this->waSendInvoice($data, $orderId, $voucher, $snap);
  //     //   }
  //     // }

  //     $this->waSendInvoice($data, $orderId, $voucher, $snap);

  //     return response()->json([
  //       'order_id'     => $orderId,
  //       'snap_token'   => $snap['token'] ?? null,
  //       'redirect_url' => $snap['redirect_url'] ?? null,
  //     ], 201);
  //   } catch (\Throwable $e) {
  //     \Log::error('snap.create.failed', ['order_id' => $orderId, 'err' => $e->getMessage()]);
  //     return response()->json(['error'=>'PAYMENT_CREATE_FAILED','message'=>$e->getMessage()], 502);
  //   }
  // }

  public function checkoutSnap(Request $r)
  {
    // Inisialisasi Midtrans
    \Midtrans\Config::$serverKey    = config('midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', '');
    \Midtrans\Config::$isProduction = (bool) (config('midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false));
    if (property_exists(\Midtrans\Config::class, 'isSanitized')) {
      \Midtrans\Config::$isSanitized = true;
    }

    $data = $r->validate([
      'amount'     => 'nullable|integer|min:1000', // <- tak pakai lagi, kita kunci ke harga voucher
      'name'       => 'nullable|string|max:100',
      'email'      => 'nullable|email',
      'phone'      => 'nullable|string|max:30',
      'voucher_id' => 'required|integer',
      'client_id'  => 'nullable|string|max:50',
      // 'enabled_payments' => 'array', // opsional dari FE
    ]);

    // --- Resolve Client
    $clientFromPayload = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($data['client_id'] ?? '')));
    $clientId = null;
    if ($clientFromPayload !== '') {
      $row = \DB::table('clients')
        ->where('is_active', 1)
        ->where(function($w) use ($clientFromPayload) {
          $w->where('client_id', $clientFromPayload)
            ->orWhere('slug', $clientFromPayload);
        })
        ->select('client_id','name')
        ->first();
      if ($row) $clientId = $row->client_id;
    }
    if (!$clientId) {
      $clientId = \App\Support\ClientResolver::resolve($r);
    }

    // --- Validasi voucher milik client
    $voucher = \App\Models\HotspotVoucher::query()
      ->where('id', (int)$data['voucher_id'])
      ->forClient($clientId)
      ->where('is_active', true)
      ->first();

    if (!$voucher) {
      return response()->json([
        'error'   => 'INVALID_VOUCHER',
        'message' => 'Voucher tidak tersedia untuk lokasi ini.'
      ], 422);
    }

    // Ambil info client utk ditaruh di item_details (merchant_name)
    $clientRow = \DB::table('clients')->where('client_id', $clientId)->select('name')->first();
    $merchantName = $clientRow->name ?? 'AdaPay';

    $orderId = OrderId::make($clientId);
    $gross   = (int) $voucher->price; // kunci ke harga voucher agar match di detail Snap

    // --- Payload Snap: tambahkan item_details biar produk muncul
    $payload = [
      'transaction_details' => [
        'order_id'     => $orderId,
        'gross_amount' => $gross,
      ],
      'item_details' => [[
        'id'            => 'VCHR-'.$voucher->id,
        'price'         => $gross,
        'quantity'      => 1,
        'name'          => \Illuminate\Support\Str::limit($voucher->name ?? ('Voucher #'.$voucher->id), 50),
        'category'      => 'Wifi Hotspot',
        'merchant_name' => $merchantName,
      ]],
      'customer_details' => [
        'first_name' => $data['name']  ?? null,
        'email'      => $data['email'] ?? null,
        'phone'      => $data['phone'] ?? null,
      ],

      // Pastikan QRIS ditambahkan dalam channel pembayaran
      'enabled_payments' => ['other_qris', 'gopay', 'shopeepay'],

      'callbacks' => [
        'finish' => url('/hotspot/order/'.$orderId),
      ],
      'expiry' => [
        'unit'     => 'minutes',
        'duration' => 30,
      ],
      'custom_field1' => $clientId,
      'custom_field2' => (string) $voucher->id,
      'custom_field3' => 'adapay-hotspot',
    ];

    try {
      // Buat transaksi Snap
      $respObj = \Midtrans\Snap::createTransaction($payload);
      $snap    = is_array($respObj) ? $respObj : json_decode(json_encode($respObj), true);

      // Fallback token bila SDK tidak mengembalikan
      if (empty($snap['token'])) {
        if (method_exists(\Midtrans\Snap::class, 'createTransactionToken')) {
          $snap['token'] = \Midtrans\Snap::createTransactionToken($payload);
        } elseif (method_exists(\Midtrans\Snap::class, 'getSnapToken')) {
          $snap['token'] = \Midtrans\Snap::getSnapToken($payload);
        }
      }

      \Log::info($snap);

      // Simpan Hotspot Order
      \App\Models\HotspotOrder::updateOrCreate(
        ['order_id' => $orderId],
        [
          'client_id'          => $clientId,
          'hotspot_voucher_id' => $voucher->id,
          'buyer_name'         => $data['name']  ?? null,
          'buyer_email'        => $data['email'] ?? null,
          'buyer_phone'        => $data['phone'] ?? null,
        ]
      );

      // Simpan Payment (status awal PENDING; Snap create tidak ada 'status')
      \App\Models\Payment::updateOrCreate(
        ['order_id' => $orderId],
        [
          'client_id' => $clientId,
          'provider'  => 'midtrans',
          'amount'    => $gross,
          'currency'  => 'IDR',
          'status'    => 'PENDING',
          'qr_string' => $snap['qr_string'] ?? null,
          'raw'       => $snap,
          'actions'   => $snap['actions'] ?? null,
        ]
      );

      // Kirim invoice via WhatsApp (idempotent di dalam waSendInvoice)
      $this->waSendInvoice($data, $orderId, $voucher, $snap);

      return response()->json([
        'order_id'     => $orderId,
        'snap_token'   => $snap['token'] ?? null,
        'redirect_url' => $snap['redirect_url'] ?? null,
      ], 201);

    } catch (\Throwable $e) {
      \Log::error('snap.create.failed', ['order_id' => $orderId ?? null, 'err' => $e->getMessage()]);
      return response()->json(['error'=>'PAYMENT_CREATE_FAILED','message'=>$e->getMessage()], 502);
    }
  }

  public function credentials(string $orderId, HotspotProvisioner $prov)
  {
    $user = $prov->provision($orderId) ?: HotspotUser::where('order_id', $orderId)->first();  
    if (!$user) return response()->json(['ready' => false]);
    $mode = ($user->username === $user->password) ? 'code' : 'userpass';

    return response()->json([
      'ready' => true,
      'mode' => $mode,
      'username' => $user->username,
      'password' => $user->password,
      'profile' => $user->profile,
      'duration_minutes' => $user->duration_minutes,
    ]);
  }

  public function vouchers(Request $request)
  {
    $q = trim((string) $request->query('client', ''));
    $clientId = 'DEFAULT';

    if ($q !== '') {
      $row = DB::table('clients')
        ->where('is_active', 1)
        ->where(function($w) use ($q) {
          $w->where('client_id', $q)->orWhere('slug', $q);
        })
        ->select('client_id')
        ->first();
      $clientId = $row ? $row->client_id : strtoupper(preg_replace('/[^A-Z0-9]/', '', $q));
    }

    $vouchers = \App\Models\HotspotVoucher::listForPortal($clientId);

    $data = $vouchers->map(function($v){
      return [
        'id'    => (int) $v->id,
        'name'  => $v->name,
        'price' => (int) $v->price,
      ];
    })->values();

    return response()->json([
      'ok'        => true,
      'client_id' => $clientId,
      'count'     => $data->count(),
      'data'      => $data,
    ]);
  }

  private function normalizeMethod(string $method, ?string $issuer = null): string
  {
    $m = strtoupper($method);
    $map = [
      'QRIS' => 'QRIS',
      'GOPAY' => 'GoPay',
      'SHOPEEPAY' => 'ShopeePay',
      'OVO' => 'OVO',
      'BANK_TRANSFER' => 'Transfer Bank',
      'CREDIT_CARD' => 'Kartu Kredit',
    ];
    $nice = $map[$m] ?? ucwords(strtolower($m));
    $iss  = $issuer ? ucwords(strtolower($issuer)) : null;

    // Contoh: "QRIS · GoPay" kalau issuer ada
    return $iss ? "{$nice} · {$iss}" : $nice;
  }

  private function labelPaymentStatus(string $status): string
  {
    $s = strtoupper($status);
    switch ($s) {
      case 'SETTLEMENT':
      case 'CAPTURE':
        return 'LUNAS';
      case 'PENDING':
      case 'INIT':
        return 'Menunggu Pembayaran';
      case 'EXPIRE':
      case 'EXPIRED':
        return 'Kedaluwarsa';
      case 'CANCEL':
      case 'DENY':
        return 'Dibatalkan';
      default:
        return ucwords(strtolower($s));
    }
  }

  private function fmtWib(?string $ts): ?string
  {
    if (!$ts) return null;
    try {
      // Midtrans biasanya "YYYY-MM-DD HH:mm:ss"
      $dt = Carbon::parse($ts, 'Asia/Jakarta')->timezone('Asia/Jakarta');
      // 06 Sep 2025 09:44 WIB
      return $dt->translatedFormat('d M Y H:i') . ' WIB';
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * Kirim invoice WA jika phone tersedia.
   */
  private function waSendInvoice(array $data, string $orderId, $voucher, array $resp): void
  {
    try {
      // --- Phone fallback ---
      $phone = $data['phone'] ?? $data['buyer_phone'] ?? null;
      if (!$phone) {
        $ord = \DB::table('hotspot_orders')->where('order_id',$orderId)->first();
        $phone = $ord->buyer_phone ?? null;
      }
      if (!$phone) { \Log::warning('wa.invoice.no_phone', compact('orderId')); return; }

      // --- Idempotent: satu kunci saja ---
      $cacheKey = "wa:invoice:{$orderId}";
      if (!\Cache::add($cacheKey, 1, now()->addMinutes(30))) {
        \Log::info('wa.invoice.skip.cache', compact('orderId'));
        return;
      }

      $to       = \App\Support\Phone::normalizePhone($phone);
      $orderUrl = url("/hotspot/order/{$orderId}");
      $payUrl   = $this->extractPayActionUrl($resp) ?? $orderUrl;

      $amount = (int) round((float) ($resp['gross_amount'] ?? $voucher->price));
      $method = strtoupper($resp['payment_type'] ?? ($data['method'] ?? ''));
      $status = strtoupper($resp['transaction_status'] ?? ($resp['status'] ?? 'PENDING'));

      $msg = $this->buildWaInvoiceMessage([
        'order_id'   => $orderId,
        'voucher'    => $voucher->name ?? ("Voucher #{$voucher->id}"),
        'amount'     => $amount,
        'method'     => $method,
        'issuer'     => $resp['issuer'] ?? null,
        'status'     => $status,
        'pay_url'    => $payUrl,
        'order_url'  => $orderUrl,
        'tx' => [
          'transaction_time'   => $resp['transaction_time']  ?? null,
          'settlement_time'    => $resp['settlement_time']   ?? null,
          'expiry_time'        => $resp['expiry_time']       ?? null,
          'payment_type'       => $resp['payment_type']      ?? null,
          'transaction_status' => $status,
        ],
      ]);

      // --- Kirim: after_response dulu, lalu fallback direct ---
      try {
        \App\Jobs\SendWhatsAppMessage::dispatchAfterResponse($to, $msg, $orderId);
        \Log::info('wa.invoice.after_response.dispatched', compact('orderId','to'));
      } catch (\Throwable $e) {
        \Log::warning('wa.invoice.after_response.failed', ['order_id'=>$orderId,'err'=>$e->getMessage()]);
        app(\App\Services\WhatsAppGateway::class)->send($to, $msg);
        \Log::info('wa.invoice.sent.direct', compact('orderId','to'));
      }

      \DB::table('payments')
        ->where('order_id',$orderId)
        ->update(['notified_invoice_at'=>now()]);

    } catch (\Throwable $e) {
      \Log::warning('hotspot.invoice.whatsapp_failed', ['order_id' => $orderId, 'err' => $e->getMessage()]);
      // lepas kunci biar bisa dicoba ulang
      \Cache::forget("wa:invoice:{$orderId}");
    }
  }

  private function extractPayActionUrl(array $resp): ?string
  {
    $actions = $resp['actions'] ?? [];

    $candidates = [
      $resp['redirect_url']            ?? null,
      $resp['finish_redirect_url']     ?? null,
      $resp['deeplink_url']            ?? null,
      $resp['mobile_deeplink']         ?? null,
      $resp['qr_url']                  ?? null,
      $resp['snap']['redirect_url']    ?? null,
      is_array($actions) && isset($actions['redirect_url']) ? $actions['redirect_url'] : null,
      is_array($actions) && isset($actions['deeplink_url']) ? $actions['deeplink_url'] : null,
      is_array($actions) && isset($actions['mobile_deeplink']) ? $actions['mobile_deeplink'] : null,
      is_array($actions) && isset($actions[0]['url']) ? $actions[0]['url'] : null,
    ];

    foreach ($candidates as $u) {
      if (is_string($u) && strlen($u) > 8) {
        return $u;
      }
    }
    return null;
  }

  private function buildWaInvoiceMessage(array $d): string
  {
    $rp = fn(int $n) => 'Rp ' . number_format($n, 0, ',', '.');

    $status = strtoupper($d['status'] ?? 'PENDING');
    $label  = $this->labelPaymentStatus($status);

    $issuer = trim((string) ($d['issuer'] ?? ''));
    $methodNice = $this->normalizeMethod($d['method'] ?? 'QRIS', $issuer);

    // Timestamps (WIB)
    $tx         = $d['tx'] ?? [];
    $txAt       = $this->fmtWib($tx['transaction_time'] ?? null);
    $settledAt  = $this->fmtWib($tx['settlement_time']  ?? null);
    $expiresAt  = $this->fmtWib($tx['expiry_time']      ?? null);

    $isPaid   = in_array($status, ['SETTLEMENT','CAPTURE'], true);
    $isExpire = in_array($status, ['EXPIRE','EXPIRED'], true);
    $isPend   = in_array($status, ['PENDING','INIT'], true);

    $lines = [];

    // Header dinamis
    if ($isPaid) {
      $lines[] = "*✅ Pembayaran Berhasil*";
    } elseif ($isExpire) {
      $lines[] = "*⛔ Pembayaran Kedaluwarsa*";
    } elseif ($isPend) {
      $lines[] = "*Invoice WiFi Hotspot*";
    } else {
      $lines[] = "*Status Pembayaran*";
    }

    // Detail utama
    $lines[] = "Order ID : {$d['order_id']}";
    $lines[] = "Produk   : {$d['voucher']}";
    $lines[] = "Harga    : " . $rp((int) $d['amount']);
    $lines[] = "Metode   : {$methodNice}";
    $lines[] = "Status   : {$label}";

    // Waktu penting
    if ($txAt)      $lines[] = "Dibuat   : {$txAt}";
    if ($isPaid && $settledAt) $lines[] = "Dibayar  : {$settledAt}";
    if ($isPend && $expiresAt) $lines[] = "Berlaku  : s.d. {$expiresAt}";

    $lines[] = ""; // spacer

    // Aksi / tautan
    if ($isPaid) {
      $lines[] = "Lihat detail order:";
      $lines[] = $d['order_url'];
    } elseif (!$isExpire) {
      $lines[] = "👉 Bayar / lihat instruksi:";
      $lines[] = $d['pay_url'];
      $lines[] = "";
      $lines[] = "Pantau status order:";
      $lines[] = $d['order_url'];
    } else {
      $lines[] = "Buat order baru:";
      $lines[] = $d['order_url'];
    }

    $lines[] = "";
    $lines[] = "_Terima kasih_ 🙏";

    // Rapikan & hapus baris kosong berlebihan
    return implode("\n", array_values(array_filter($lines, fn($l) => $l !== null)));
  }
}
