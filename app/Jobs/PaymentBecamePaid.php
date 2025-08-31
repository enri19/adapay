<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\HotspotUser;
use App\Models\HotspotOrder;
use App\Models\Client;
use App\Services\HotspotProvisioner;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentBecamePaid implements ShouldQueue, ShouldBeUnique
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  /** Jalan di antrean router */
  public $queue = 'router';

  /** Cegah duplikasi job untuk order yang sama */
  public $uniqueFor = 3600; // detik (1 jam)

  /** @var string */
  protected $orderId;

  public $tries = 5;
  public function backoff()
  {
    return [10, 30, 60, 120, 300];
  }

  public function __construct(string $orderId)
  {
    $this->orderId = $orderId;
  }

  public function uniqueId()
  {
    return $this->orderId;
  }

  public function handle(HotspotProvisioner $prov): void
  {
    $p = Payment::where('order_id', $this->orderId)->first();
    if (!$p || $p->status !== Payment::S_PAID) return;

    // 1) Provision user (idempotent)
    $u = HotspotUser::where('order_id', $this->orderId)->first();
    if (!$u) {
      $u = $prov->provision($this->orderId);
    }

    // 2) Push ke Mikrotik (respect enable_push)
    if ($u) {
      $prov->pushToMikrotik($u);
    }

    // 3) Kirim WhatsApp sekali saja
    $gateKey = 'wa:paid:sent:' . $this->orderId;
    if (Cache::add($gateKey, 1, now()->addDay())) {
      $this->dispatchPaidWhatsApp($this->orderId, $u);
    }

    Log::info('PaymentBecamePaid done', ['order_id' => $this->orderId]);
  }

  protected function dispatchPaidWhatsApp(string $orderId, ?HotspotUser $u): void
  {
    $order = HotspotOrder::where('order_id', $orderId)->first();
    if (!$order || !$order->buyer_phone) return;

    $client = $order->client_id
      ? Client::where('client_id', $order->client_id)->first()
      : null;

    $authMode = $client && $client->auth_mode ? $client->auth_mode : 'userpass';
    $to = \App\Support\Phone::normalizePhone($order->buyer_phone);

    $orderUrl = url('/hotspot/order/' . $orderId);
    if ($u) {
      $isCode = ($authMode === 'code') || (strtoupper($u->username) === strtoupper($u->password));
      $dur = $u->duration_minutes ? ($u->duration_minutes . ' menit') : '-';
      $msg = implode("\n", array_filter([
        '*Pembayaran Berhasil ✅*',
        'Order ID : ' . $orderId,
        'Profile  : ' . ($u->profile ?: '-') . ' (' . $dur . ')',
        '',
        $isCode
          ? "*Kode Hotspot Kamu*\nKode : `{$u->username}`\n\nGunakan kode tersebut sebagai *Username* dan *Password* saat login."
          : "*Akun Hotspot Kamu*\nUsername : `{$u->username}`\nPassword : `{$u->password}`",
        '',
        'Kamu bisa login sekarang.',
        'Cek kembali di: ' . $orderUrl,
        '_Terima kasih_ 🙏',
      ]));
    } else {
      $msg = implode("\n", [
        '*Pembayaran Berhasil ✅*',
        'Order ID : ' . $orderId,
        '',
        'Akun kamu sedang disiapkan.',
        'Pantau di: ' . $orderUrl,
        '_Terima kasih_ 🙏',
      ]);
    }

    SendWhatsAppMessage::dispatch($to, $msg, $orderId)->onQueue('wa');
  }

  public function failed(\Throwable $e): void
  {
    Log::error('PaymentBecamePaid failed', [
      'order_id' => $this->orderId,
      'err' => $e->getMessage(),
    ]);
  }
}
