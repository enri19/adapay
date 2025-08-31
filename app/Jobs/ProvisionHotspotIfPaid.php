<?php

// app/Jobs/ProvisionHotspotIfPaid.php
namespace App\Jobs;

use App\Models\Payment;
use App\Services\HotspotProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProvisionHotspotIfPaid implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  /** @var string */
  protected $orderId;

  public int $tries = 5;
  public function backoff()
  {
    return [10, 30, 60, 120, 300];
  }

  public function __construct(string $orderId) {
    $this->orderId = $orderId;
  }

  public function handle(HotspotProvisioner $prov): void
  {
    $p = Payment::where('order_id', $this->orderId)->first();
    if (!$p || $p->status !== Payment::S_PAID) return;

    $u = \App\Models\HotspotUser::where('order_id',$this->orderId)->first();
    if (!$u) {
      $u = $prov->provision($this->orderId); // DB only
    }
    if ($u) {
      // dorong ke Mikrotik di queue router (cegah blokir di sini juga)
      \App\Jobs\PushHotspotUserToRouter::dispatch($u->id)->onQueue('router');
    }
  }
}
