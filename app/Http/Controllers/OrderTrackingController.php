<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderTrackingController extends Controller
{
  public function index(Request $r)
  {
    $order = null;
    if ($r->filled('order_id')) {
      $orderId = $this->cleanOrderId($r->input('order_id', ''));
      $order   = $this->findOrder($orderId);
    }
    return view('orders.track', ['order' => $order]);
  }

  public function lookup(Request $r)
  {
    $r->validate([
      'order_id' => ['required','string','max:80'],
    ]);

    $orderId = $this->cleanOrderId($r->input('order_id', ''));
    $order   = $this->findOrder($orderId);

    if (!$order) {
      return back()->withInput()->withErrors(['order_id' => 'Order tidak ditemukan. Pastikan ID benar.']);
    }

    return redirect()->route('orders.track', ['order_id' => $order->order_id]);
  }

  private function cleanOrderId($v): string
  {
    return substr(trim((string) $v), 0, 80);
  }

  /**
   * Satukan data dari hotspot_orders (pembeli) + payments (status/nominal).
   * Ambil baris payment terbaru (id terbesar) untuk order yang sama.
   */
  private function findOrder(string $orderId)
  {
    $ho = DB::table('hotspot_orders')
      ->where('order_id', $orderId)
      ->select([
        'order_id', 'client_id', 'hotspot_voucher_id',
        'buyer_name', 'buyer_email', 'buyer_phone',
        'created_at as order_created_at', 'updated_at as order_updated_at',
      ])->first();

    $pay = DB::table('payments')
      ->where('order_id', $orderId)
      ->orderByDesc('id')
      ->select([
        'id','order_id','client_id','provider','provider_ref',
        'amount','currency','status','qr_string','actions','raw',
        'paid_at','created_at as pay_created_at','updated_at as pay_updated_at',
      ])->first();

    if (!$ho && !$pay) {
      return null;
    }

    // parse JSON raw/actions bila ada
    $raw = [];
    if ($pay && $pay->raw) {
      $tmp = json_decode($pay->raw, true);
      if (is_array($tmp)) $raw = $tmp;
    }
    $actions = [];
    if ($pay && $pay->actions) {
      $tmp = json_decode($pay->actions, true);
      if (is_array($tmp)) $actions = $tmp;
    }

    // payment_type & deeplink dari raw/actions
    $paymentType = $raw['payment_type'] ?? null; // e.g. gopay, qris, shopeepay
    $deeplinkUrl = null;
    if (!empty($actions) && is_array($actions)) {
      foreach ($actions as $a) {
        // Midtrans kadang menaruh di 'name' => 'deeplink' atau 'mobile_deeplink'
        if (isset($a['name']) && stripos($a['name'], 'deeplink') !== false && !empty($a['url'])) {
          $deeplinkUrl = $a['url'];
          break;
        }
      }
    }
    // fallback: beberapa payload menaruh langsung di raw
    if (!$deeplinkUrl && isset($raw['actions']) && is_array($raw['actions'])) {
      foreach ($raw['actions'] as $a) {
        if (isset($a['name']) && stripos($a['name'], 'deeplink') !== false && !empty($a['url'])) {
          $deeplinkUrl = $a['url'];
          break;
        }
      }
    }

    // expiry time (jika ada di raw)
    $expiresAt = $raw['expiry_time'] ?? null;

    // Tampilkan metode ramah manusia
    $methodHuman = $this->humanizeMethod($pay->provider ?? null, $paymentType);

    // Susun objek final sesuai yang dipakai di Blade
    return (object) [
      'order_id'         => $orderId,
      'status'           => $pay->status ?? 'PENDING',
      'amount'           => $pay->amount ? (int) $pay->amount : null,
      'currency'         => $pay->currency ?? 'IDR',
      'payment_method'   => $methodHuman,     // contoh: "GoPay (Midtrans)" atau "QRIS (Midtrans)"
      'provider_ref'     => $pay->provider_ref ?? null,
      'created_at'       => $ho->order_created_at ?? ($pay->pay_created_at ?? null),
      'paid_at'          => $pay->paid_at ?? null,
      'expires_at'       => $expiresAt,

      // kredensial hotspot (kalau ada di tabel lain, tinggal isi di sini)
      'hotspot_username' => null,
      'hotspot_password' => null,

      // Aksi pembayaran
      'qris_url'         => null,             // bisa diisi route generator QR kalau mau
      'deeplink_url'     => $deeplinkUrl,

      // Info pembeli
      'buyer_name'       => $ho->buyer_name ?? null,
      'buyer_email'      => $ho->buyer_email ?? null,
      'buyer_phone'      => $ho->buyer_phone ?? null,

      // Simpan data mentah jika perlu debug di view
      'qr_string'        => $pay->qr_string ?? null,
      'provider'         => $pay->provider ?? null,
      'payment_type'     => $paymentType,
    ];
  }

  private function humanizeMethod($provider, $paymentType): ?string
  {
    $pt = $paymentType ? strtolower($paymentType) : null;
    $name = null;

    if ($pt === 'gopay') {
      $name = 'GoPay';
    } elseif ($pt === 'qris') {
      $name = 'QRIS';
    } elseif ($pt === 'shopeepay') {
      $name = 'ShopeePay';
    } elseif ($pt === 'bank_transfer') {
      $name = 'Bank Transfer';
    } else {
      $name = $paymentType; // biarin apa adanya kalau gak dikenali
    }

    if ($name && $provider) {
      return $name . ' (' . ucfirst($provider) . ')';
    }
    return $name ?: ($provider ? ucfirst($provider) : null);
  }
}
