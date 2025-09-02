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

    return view('hotspot.index', [
      'vouchers'         => $vouchers,
      'resolvedClientId' => $selectedClientId, // bisa null
      'isBaseHost'       => $isBaseHost,
      'clients'          => $clients,
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
    return view('hotspot.order', compact('orderId'));
  }

  public function checkout(Request $request)
  {
    $data = $request->validate([
      'voucher_id' => 'required|exists:hotspot_vouchers,id',
      'name'       => 'nullable|string|max:100',
      'email'      => 'nullable|email',
      'phone'      => 'nullable|string|max:30',
      'method'     => 'nullable|string|in:qris,gopay,shopeepay',
      'client_id'  => 'nullable|string|max:32',
    ]);

    // --- RESOLVE CLIENT SECARA TEGAS ---
    $host       = strtolower($request->getHost());
    $isBaseHost = ($host === 'pay.adanih.info');

    // 1) Utamakan client_id dari payload (boleh client_id atau slug)
    $clientFromPayload = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($data['client_id'] ?? '')));
    $clientId = null;
    if ($clientFromPayload !== '') {
        $row = DB::table('clients')
            ->where('is_active', 1)
            ->where(function($w) use ($clientFromPayload){
                $w->where('client_id', $clientFromPayload)
                  ->orWhere('slug', $clientFromPayload);
            })
            ->select('client_id')
            ->first();
        if ($row) {
            $clientId = $row->client_id; // valid dari payload
        }
    }

    // 2) Fallback ke resolver (subdomain / ?client)
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

    // (sisanya TETAP seperti punyamu)
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
            'client_id'           => $clientId,
            'hotspot_voucher_id'  => $voucher->id,
            'buyer_name'          => $data['name'] ?? null,
            'buyer_email'         => $data['email'] ?? null,
            'buyer_phone'         => $data['phone'] ?? null,
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

        $this->waSendInvoice($data, $orderId, $voucher, $resp);

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

  /**
   * Kirim invoice WA jika phone tersedia.
   */
  private function waSendInvoice(array $data, string $orderId, $voucher, array $resp): void
  {
    try {
      if (empty($data['phone'])) return;

      if (!\Cache::add('wa:invoice:'.$orderId, 1, 600)) {
        \Log::info('wa.invoice.skip.cache', compact('orderId'));
        return;
      }

      $to       = \App\Support\Phone::normalizePhone($data['phone']);
      $orderUrl = url("/hotspot/order/{$orderId}");
      $payUrl   = $this->extractPayActionUrl($resp) ?? $orderUrl;

      $msg = $this->buildWaInvoiceMessage([
        'order_id'  => $orderId,
        'voucher'   => $voucher->name ?? ("Voucher #{$voucher->id}"),
        'amount'    => (int) $voucher->price,
        'method'    => strtoupper($data['method'] ?? 'QRIS'),
        'status'    => $resp['status'] ?? 'PENDING',
        'pay_url'   => $payUrl,
        'order_url' => $orderUrl,
      ]);

      $conn = config('queue.default', 'sync');
      $dispatched = false;

      try {
        if ($conn !== 'sync') {
          \App\Jobs\SendWhatsAppMessage::dispatch($to, $msg, $orderId)->onQueue('wa');
          $dispatched = true;
        }
      } catch (\Throwable $e) {
        \Log::warning('wa.queue.dispatch_failed', ['order_id'=>$orderId,'err'=>$e->getMessage()]);
      }

      if (!$dispatched) {
        try {
          \App\Jobs\SendWhatsAppMessage::dispatchAfterResponse($to, $msg, $orderId);
          $dispatched = true;
        } catch (\Throwable $e) {
          \Log::warning('wa.after_response.failed', ['order_id'=>$orderId,'err'=>$e->getMessage()]);
        }
      }

      if (!$dispatched) {
        app(\App\Services\WhatsAppGateway::class)->send($to, $msg);
        $dispatched = true;
      }

      // Stempel hanya kalau ada upaya kirim
      if ($dispatched) {
        \DB::table('payments')
          ->where('order_id', $orderId)
          ->whereNull('notified_invoice_at')
          ->update(['notified_invoice_at' => now()]);
      }
    } catch (\Throwable $e) {
      \Log::warning('hotspot.invoice.whatsapp_failed', ['order_id' => $orderId, 'err' => $e->getMessage()]);
    }
  }

  private function extractPayActionUrl(array $resp): ?string
  {
    $candidates = [
      $resp['actions']['redirect_url']    ?? null,
      $resp['actions']['deeplink_url']    ?? null,
      $resp['actions']['mobile_deeplink'] ?? null,
      $resp['actions'][0]['url']          ?? null,
      $resp['qr_url']                     ?? null,
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

    return implode("\n", array_filter([
      "*Invoice WiFi Hotspot*",
      "Order ID : {$d['order_id']}",
      "Produk   : {$d['voucher']}",
      "Harga    : " . $rp($d['amount']),
      "Metode   : {$d['method']}",
      "Status   : {$d['status']}",
      "",
      "👉 Bayar/lihat instruksi:",
      $d['pay_url'],
      "",
      "Pantau status order:",
      $d['order_url'],
      "",
      "_Terima kasih_ 🙏",
    ]));
  }
}
