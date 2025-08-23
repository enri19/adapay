<?php
namespace App\Services\Mikrotik;

use Illuminate\Support\Facades\Log;

class NullMikrotikClient implements MikrotikClient
{
  public function createHotspotUser(string $username, string $password, ?string $profile, ?string $comment, ?string $limitUptime): void
  {
    Log::info('[MikrotikStub] createHotspotUser', [
      'username' => $username,
      'password' => $password,
      'profile' => $profile,
      'comment' => $comment,
      'limitUptime' => $limitUptime,
    ]);
  }
}
