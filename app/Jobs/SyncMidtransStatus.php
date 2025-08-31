<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Support\OrderId;

class SyncMidtransStatus implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  protected $orderId;

  public $tries = 5;
  public function backoff(){ return [5, 10, 30, 60, 120]; }

  public function __construct($orderId)
  {
    $this->orderId = (string) $orderId;
  }

  public function handle()
  {
    // init midtrans (pakai helper kamu sendiri kalau ada)
    \Midtrans\Config::$serverKey    = config('midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', '');
    \Midtrans\Config::$isProduction = (bool) (config('midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false));
    if (property_exists(\Midtrans\Config::class, 'isSanitized')) \Midtrans\Config::$isSanitized = true;

    $arr = [];
    try {
      $latest = \Midtrans\Transaction::status($this->orderId);
      $arr = is_array($latest) ? $latest : json_decode(json_encode($latest), true);
    } catch (\Throwable $e) {
      Log::warning('sync.midtrans.fetch_failed', ['order_id'=>$this->orderId, 'err'=>$e->getMessage()]);
      return; // biar retry
    }

    $rawStatus = strtolower($arr['transaction_status'] ?? 'pending');
    $incoming  = app(\App\Payments\Providers\MidtransAdapter::class)->normalizeStatus($rawStatus); // PENDING/PAID/FAILED

    $becamePaid = false;

    DB::transaction(function () use (&$becamePaid, $arr, $incoming) {
      $p = Payment::where('order_id', $this->orderId)->lockForUpdate()->first();
      if (!$p) return;

      $prev = (string) $p->status;
      $merged = method_exists(Payment::class,'mergeStatus')
        ? Payment::mergeStatus($p->status, $incoming)
        : $incoming;

      // merge raw
      $prevRaw = is_array($p->raw) ? $p->raw : json_decode(json_encode($p->raw), true);
      $mergedRaw = array_replace_recursive($prevRaw ?: [], $arr ?: []);

      $p->status = $merged;
      $p->raw    = $mergedRaw;
      if (in_array($rawStatus = strtolower($arr['transaction_status'] ?? ''), ['capture','settlement','success'], true) && !$p->paid_at) {
        $p->paid_at = now();
      }
      $p->save();

      $becamePaid = ($prev !== Payment::S_PAID) && ($merged === Payment::S_PAID);
    });

    if ($becamePaid) {
      // Provision & push dilakukan via job, WA via job → anti dobel
      \App\Jobs\ProvisionHotspotIfPaid::dispatch($this->orderId)->onQueue('router');
      \App\Jobs\PaymentBecamePaid::dispatch($this->orderId)->onQueue('wa');
      Log::info('sync.midtrans.became_paid', ['order_id'=>$this->orderId]);
    }
  }
}
