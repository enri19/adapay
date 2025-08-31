<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\HotspotUser;
use App\Services\HotspotProvisioner;

class ProvisionHotspotIfPaid implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  protected $orderId;

  public $tries = 5;
  public function backoff(){ return [5, 10, 30, 60, 120]; }

  public function __construct($orderId)
  {
    $this->orderId = (string) $orderId;
  }

  public function handle(HotspotProvisioner $prov)
  {
    $p = Payment::where('order_id', $this->orderId)->first();
    if (!$p || $p->status !== Payment::S_PAID) return;

    $u = HotspotUser::where('order_id', $this->orderId)->first();
    if ($u) {
      // dorong ulang ke router bila perlu
      \App\Jobs\PushHotspotUserToRouter::dispatch($u->id)->onQueue('router');
      return;
    }

    $created = $prov->provision($this->orderId);
    if ($created) {
      \App\Jobs\PushHotspotUserToRouter::dispatch($created->id)->onQueue('router');
      Log::info('provision.created', ['order_id'=>$this->orderId, 'u'=>$created->username]);
    } else {
      Log::warning('provision.skipped', ['order_id'=>$this->orderId]);
    }
  }
}
