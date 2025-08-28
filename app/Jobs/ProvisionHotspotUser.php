<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\RouterOsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionHotspotUser implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  /** @var int */
  protected $clientId;
  /** @var string */
  protected $username;
  /** @var string */
  protected $password;
  /** @var string|null */
  protected $profile;
  /** @var string|null */
  protected $limitUptime;

  /**
   * Retriable: 5x
   * (di Laravel 8, properti $backoff kadang belum dikenali, jadi pakai method)
   */
  public $tries = 5;
  public function backoff()
  {
    return [10, 30, 60, 120, 300]; // detik
  }

  public function __construct($clientId, $username, $password, $profile = null, $limitUptime = null)
  {
    $this->clientId = (int) $clientId;
    $this->username = (string) $username;
    $this->password = (string) $password;
    $this->profile = $profile ? (string) $profile : null;
    $this->limitUptime = $limitUptime ? (string) $limitUptime : null;
  }

  public function handle()
  {
    $client = Client::findOrFail($this->clientId);

    $port = (int) ($client->router_port ?: 8728);
    $tls  = $port === 8729;

    $svc  = new RouterOsService(
      (string) $client->router_host,
      $port,
      (string) $client->router_user,
      (string) $client->router_pass, // didekripsi otomatis oleh model trait
      $tls
    );

    // Idempotent: skip jika sudah ada
    if ($svc->hotspotUserExists($this->username)) {
      Log::info('ProvisionHotspotUser: already exists', ['client_id' => $this->clientId, 'u' => $this->username]);
      return;
    }

    $svc->addHotspotUser([
      'name' => $this->username,
      'password' => $this->password,
      'profile' => $this->profile,
      'limit-uptime' => $this->limitUptime,
    ]);

    Log::info('ProvisionHotspotUser: added', ['client_id' => $this->clientId, 'u' => $this->username]);
  }

  public function failed(\Throwable $e)
  {
    Log::error('ProvisionHotspotUser failed', [
      'client_id' => $this->clientId,
      'u' => $this->username,
      'err' => $e->getMessage(),
    ]);
  }
}
