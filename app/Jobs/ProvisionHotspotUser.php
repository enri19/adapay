<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\Mikrotik\MikrotikClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionHotspotUser implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  /** Jalankan di antrean "router" by default */
  public $queue = 'router';

  /** Retry policy */
  public $tries = 5;
  public function backoff() { return [10, 30, 60, 120, 300]; } // detik

  /** Payload */
  protected $clientId;
  protected $username;
  protected $password;
  protected $profile;
  protected $limitUptime;
  protected $comment;

  /**
   * @param int         $clientId     ID tabel clients
   * @param string      $username
   * @param string      $password
   * @param string|null $profile      contoh: 'default'
   * @param string|null $limitUptime  contoh: '60m' / '1h'
   * @param string|null $comment      opsional, default diisi otomatis
   */
  public function __construct($clientId, $username, $password, $profile = null, $limitUptime = null, $comment = null)
  {
    $this->clientId    = (int) $clientId;
    $this->username    = (string) $username;
    $this->password    = (string) $password;
    $this->profile     = $profile ? (string) $profile : null;
    $this->limitUptime = $limitUptime ? (string) $limitUptime : null;
    $this->comment     = $comment ? (string) $comment : null;
  }

  /**
   * Pakai driver baru: App\Services\Mikrotik\MikrotikClient
   * Method createHotspotUser() di driver kamu sudah idempotent (update kalau sudah ada).
   */
  public function handle(MikrotikClient $mt): void
  {
    $client = Client::findOrFail($this->clientId);

    // Validasi konfigurasi router
    $cfg = [
      'host'    => (string) $client->router_host,
      'port'    => (int)   ($client->router_port ?: 8728),
      'user'    => (string) $client->router_user,
      'pass'    => (string) $client->router_pass,
      'timeout' => 10,
      'ssl'     => ((int) ($client->router_port ?: 8728) === 8729),
    ];
    if ($cfg['host'] === '' || $cfg['user'] === '' || $cfg['pass'] === '') {
      throw new \RuntimeException('Router config incomplete (host/user/pass).');
    }

    // Dapatkan instance Mikrotik yang sudah “terkonfigurasi”
    if (method_exists($mt, 'withConfig')) {
      $mt = $mt->withConfig($cfg);
    } elseif (method_exists($mt, 'connect')) {
      $mt->connect($cfg['host'], $cfg['port'], $cfg['user'], $cfg['pass']);
    }

    // Optional ping ringan (abaikan kalau gagal)
    if (method_exists($mt, 'ping')) {
      try { $mt->ping(); } catch (\Throwable $e) { /* ignore */ }
    }

    $comment = $this->comment ?: ('provision via queue '.now()->format('Y-m-d H:i:s'));

    // Ini sudah idempotent di driver: kalau user ada → di-update; kalau tidak → add
    $mt->createHotspotUser(
      strtoupper($this->username),
      strtoupper($this->password),
      $this->profile ?: ($client->default_profile ?: 'default'),
      $comment,
      $this->limitUptime
    );

    Log::info('ProvisionHotspotUser: ok', [
      'client_id' => $this->clientId,
      'username'  => $this->username,
      'profile'   => $this->profile ?: $client->default_profile ?: 'default',
      'limit'     => $this->limitUptime,
    ]);
  }

  public function failed(\Throwable $e): void
  {
    Log::error('ProvisionHotspotUser failed', [
      'client_id' => $this->clientId,
      'u'         => $this->username,
      'err'       => $e->getMessage(),
    ]);
  }
}
