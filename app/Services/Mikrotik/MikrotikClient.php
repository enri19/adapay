<?php
namespace App\Services\Mikrotik;

interface MikrotikClient
{
  public function createHotspotUser(string $username, string $password, ?string $profile, ?string $comment, ?string $limitUptime): void;
}
