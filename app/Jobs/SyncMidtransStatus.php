<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Payments\Providers\MidtransAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMidtransStatus implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

  public function handle(): void
  {
    \Midtrans\Config::$serverKey    = config('midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', '');
    \Midtrans\Config::$isProduction = (bool) (config('midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false));
    if (property_exists(\Midtrans\Config::class, 'isSanitized')) {
      \Midtrans\Config::$isSanitized = true;
    }

    $p = Payment::where('order_id', $this->orderId)->first();
    if (!$p) return;

    $prev = $p->status;

    $latest = \Midtrans\Transaction::status($this->orderId);
    $arr = is_array($latest) ? $latest : json_decode(json_encode($latest), true);

    $rawStatus = strtolower($arr['transaction_status'] ?? 'pending');
    $incoming  = app(MidtransAdapter::class)->normalizeStatus($rawStatus);

    $p->status = Payment::mergeStatus($prev, $incoming);
    $p->raw    = array_merge(is_array($p->raw) ? $p->raw : [], $arr);
    if (in_array($rawStatus, ['capture','settlement','success'], true) && !$p->paid_at) {
      $p->paid_at = now();
    }
    $p->save();

    if ($prev !== Payment::S_PAID && $p->status === Payment::S_PAID) {
      \App\Jobs\PaymentBecamePaid::dispatch($this->orderId)->onQueue('router');
    }
  }
}
