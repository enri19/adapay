<?php

namespace App\Jobs;

use App\Services\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public string $to;
  public string $message;
  public ?string $orderId;

  public int $tries = 5;
  public $backoff = [10, 30, 120]; // detik

  public function __construct(string $to, string $message, ?string $orderId = null)
  {
    $this->to = $to;
    $this->message = $message;
    $this->orderId = $orderId;
  }

  public function middleware()
  {
    // Optional: batasi rate kalau gateway sensitif
    return [new RateLimited('whatsapp')];
  }

  public function handle(WhatsAppGateway $wa): void
  {
    $wa->send($this->to, $this->message);
    Log::info('wa.send.ok', ['order_id' => $this->orderId, 'to' => $this->to]);
  }

  public function failed(\Throwable $e): void
  {
    Log::warning('wa.send.failed', [
      'order_id' => $this->orderId,
      'to'       => $this->to,
      'err'      => $e->getMessage(),
    ]);
  }
}
