<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use App\Models\HotspotOrder;
use App\Models\HotspotUser;
use App\Services\WhatsAppGateway;
use App\Support\Phone;

class PaymentBecamePaid implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  protected $orderId;

  public $tries = 5;
  public function backoff(){ return [5, 10, 30, 60, 120]; }

  public function __construct($orderId)
  {
    $this->orderId = (string) $orderId;
  }

  // (opsional) kalau mau memaksa queue di level job:
  // public function viaQueue(){ return 'wa'; }

  public function handle(WhatsAppGateway $wa)
  {
    $order = HotspotOrder::where('order_id', $this->orderId)->first();
    if (!$order || !$order->buyer_phone) return;

    $user = HotspotUser::where('order_id', $this->orderId)->first();
    $to   = Phone::normalizePhone($order->buyer_phone);

    // ambil auth_mode
    $client = \App\Models\Client::where('client_id', $order->client_id)->first();
    $authMode = $client ? $client->auth_mode : 'userpass';

    $msg = $user
      ? $this->buildWaPaidWithCredsMessage(
          $this->orderId,
          $user->username,
          $user->password,
          $user->profile,
          (int)$user->duration_minutes,
          $authMode
        )
      : $this->buildWaPaidSimpleMessage($this->orderId);

    $wa->send($to, $msg);
    Log::info('wa.paid.sent', ['order_id'=>$this->orderId, 'to'=>$to]);
  }

  private function buildWaPaidSimpleMessage($orderId)
  {
    $orderUrl = url("/hotspot/order/{$orderId}");
    return implode("\n", [
      "*Pembayaran Berhasil ✅*",
      "Order ID : {$orderId}",
      "",
      "Akun kamu sedang disiapkan.",
      "Pantau di: {$orderUrl}",
      "_Terima kasih_ 🙏",
    ]);
  }

  private function buildWaPaidWithCredsMessage($orderId,$username,$password,$profile,$duration,$authMode='userpass')
  {
    $orderUrl = url("/hotspot/order/{$orderId}");
    $dur = $duration ? "{$duration} menit" : '-';
    $isCode = ($authMode === 'code') || (strtoupper($username) === strtoupper($password));

    $header = [
      "*Pembayaran Berhasil ✅*",
      "Order ID : {$orderId}",
      "Profile  : {$profile} ({$dur})",
      ""
    ];

    $creds = $isCode
      ? ["*Kode Hotspot Kamu*","Kode : `{$username}`","","Gunakan kode tersebut sebagai *Username* dan *Password* saat login."]
      : ["*Akun Hotspot Kamu*","Username : `{$username}`","Password : `{$password}`"];

    $footer = ["","Kamu bisa login sekarang.","Cek kembali di: {$orderUrl}","_Terima kasih_ 🙏"];

    return implode("\n", array_merge($header,$creds,$footer));
  }
}
