<?php

namespace App\Services;

use RouterOS\Client;
use RouterOS\Query;

class RouterOsService
{
  /** @var \RouterOS\Client */
  protected $client;

  public function __construct($host, $port, $user, $pass, $tls = false)
  {
    $this->client = new Client([
      'host' => $host,
      'port' => (int) $port,
      'user' => $user,
      'pass' => $pass,
      'timeout' => 10,
      'legacy' => false,
      'attempts' => 1,
      'ssl' => (bool) $tls,
    ]);
  }

  public function hotspotUserExists($username)
  {
    $q = (new Query('/ip/hotspot/user/print'))->where('name', (string) $username);
    $res = $this->client->query($q)->read();
    return !empty($res);
  }

  public function addHotspotUser(array $payload)
  {
    $q = new Query('/ip/hotspot/user/add');
    foreach ($payload as $k => $v) {
      if ($v !== null && $v !== '') $q->equal($k, $v);
    }
    $this->client->query($q)->read();
  }
}
