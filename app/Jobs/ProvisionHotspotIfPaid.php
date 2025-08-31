<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\HotspotProvisioner;

class ProvisionHotspotIfPaid implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $tries = 3;
  protected $orderId;

  public function __construct($orderId)
  {
    $this->orderId = (string) $orderId; // tanpa promoted property biar aman di PHP < 8
  }

  public function backoff()
  {
    return [10, 30, 60];
  }

  public function handle(HotspotProvisioner $prov): void
  {
    $u = $prov->provision($this->orderId);    // buat HotspotUser (idempotent)
    if ($u) {
      $prov->queuePushToMikrotik($u);         // antrikan push ke router (job ProvisionHotspotUser)
    }
  }
}
