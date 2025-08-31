<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\HotspotProvisioner;
use App\Models\Payment;

class PaymentBecamePaid implements ShouldQueue, ShouldBeUnique
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public string $orderId;

  public $tries = 5;
  public function backoff(){ return [5,10,30,60,120]; }

  // cegah job kembar di antrean
  public function uniqueId(){ return 'paid:'.$this->orderId; }
  public $uniqueFor = 120; // 2 menit

  public function __construct(string $orderId)
  {
    $this->orderId = $orderId;
  }

  public function handle(HotspotProvisioner $prov)
  {
    // Guard cepat: pastikan benar-benar PAID
    $p = Payment::where('order_id', $this->orderId)->first();
    if (!$p || $p->status !== Payment::S_PAID) return;

    // Guard anti-dobel berbasis cache (cepat). Optional: pakai kolom DB notified_paid_at untuk persist.
    if (!Cache::add('paid:notify:'.$this->orderId, 1, 10*60)) {
      Log::info('paid.guard.cache.hit', ['order_id'=>$this->orderId]);
      return;
    }

    // 1) Provision (idempotent; akan return existing kalau sudah ada)
    $u = $prov->provision($this->orderId);

    // 2) Push ke Mikrotik lewat job unik (supaya tidak block di sini)
    if ($u) $prov->queuePushToMikrotik($u);

    // 3) Kirim WA invoice/creds via job WA (non-blocking)
    \App\Jobs\SendWhatsAppPaid::dispatch($this->orderId)->onQueue('wa');

    Log::info('paid.pipeline.done', ['order_id'=>$this->orderId]);
  }
}
