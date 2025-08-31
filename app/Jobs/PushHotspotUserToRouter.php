<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\HotspotUser;
use App\Services\HotspotProvisioner;

class PushHotspotUserToRouter implements ShouldQueue, ShouldBeUnique
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  protected int $hotspotUserId;
  public $tries = 5;
  public function backoff(){ return [5,10,30,60,120]; }

  public function uniqueId(){ return 'push:hotspot:'.$this->hotspotUserId; }
  public $uniqueFor = 60;

  public function __construct(int $hotspotUserId)
  {
    $this->hotspotUserId = $hotspotUserId;
  }

  public function handle(HotspotProvisioner $prov)
  {
    $u = HotspotUser::find($this->hotspotUserId);
    if (!$u) return;

    $prov->pushToMikrotik($u);
    Log::info('router.push.ok', ['order_id'=>$u->order_id, 'username'=>$u->username]);
  }
}
