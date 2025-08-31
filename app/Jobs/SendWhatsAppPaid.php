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
use App\Models\Client;
use App\Support\Phone;

class SendWhatsAppPaid implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public string $orderId;

  public $tries = 5;
  public function backoff(){ return [5,10,30,60,120]; }

  public function __construct(string $orderId)
  {
    $this->orderId = $orderId;
  }

  public function handle(): void
  {
    $order = HotspotOrder::where('order_id', $this->orderId)->first();
    if (!$order || !$order->buyer_phone) {
      Log::warning('wa.paid.no_phone', ['order_id' => $this->orderId]);
      return;
    }

    $to   = Phone::normalizePhone($order->buyer_phone);
    $user = HotspotUser::where('order_id', $this->orderId)->first();
    $client = Client::where('client_id', $order->client_id)->first();

    // Tentukan authMode
    $authMode = $client ? strtolower((string)$client->auth_mode) : null;
    if (!in_array($authMode, ['code','userpass'], true)) {
      $authMode = ($user && $user->username === $user->password) ? 'code' : 'userpass';
    }

    // Susun pesan
    $msg = $user
      ? $this->buildWaPaidWithCredsMessage(
          $this->orderId,
          $user->username,
          $user->password,
          $user->profile,
          (int) $user->duration_minutes,
          $authMode
        )
      : $this->buildWaPaidSimpleMessage($this->orderId);

    // Kirim lewat job pengirim yang sudah ada
    SendWhatsAppMessage::dispatch($to, $msg, $this->orderId)->onQueue('wa');

    Log::info('wa.paid.enqueued', ['order_id' => $this->orderId, 'to' => $to]);
  }

  private function buildWaPaidSimpleMessage(string $orderId): string
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

  private function buildWaPaidWithCredsMessage(
    string $orderId,
    string $username,
    string $password,
    ?string $profile,
    int $duration,
    string $authMode = 'userpass'
  ): string
  {
    $orderUrl = url("/hotspot/order/{$orderId}");
    $dur = $duration ? "{$duration} menit" : '-';

    $header = [
      "*Pembayaran Berhasil ✅*",
      "Order ID : {$orderId}",
      "Profile  : {$profile} ({$dur})",
      ""
    ];

    $isCode = ($authMode === 'code') || ($username === $password);

    $creds = $isCode
      ? [
          "*Kode Hotspot Kamu*",
          "Kode : `{$username}`",
          "",
          "Gunakan kode tersebut sebagai *Username* dan *Password* saat login."
        ]
      : [
          "*Akun Hotspot Kamu*",
          "Username : `{$username}`",
          "Password : `{$password}`"
        ];

    $footer = [
      "",
      "Kamu bisa login sekarang.",
      "Cek kembali di: {$orderUrl}",
      "_Terima kasih_ 🙏",
    ];

    return implode("\n", array_merge($header, $creds, $footer));
  }

  public function failed(\Throwable $e): void
  {
    Log::warning('wa.paid.job_failed', ['order_id' => $this->orderId, 'err' => $e->getMessage()]);
  }
}
