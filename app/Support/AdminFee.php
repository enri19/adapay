<?php

namespace App\Support;

use App\Models\Client;

class AdminFee
{
  /**
   * Ambil admin fee flat untuk sebuah client.
   * Prioritas: kolom di DB client > config map per client > default config
   */
  public static function forClient(?string $clientId): int
  {
    $clientId = $clientId ?: 'DEFAULT';

    // 1) coba dari DB (kalau tabel/kolom ada)
    $fee = null;
    if (class_exists(Client::class)) {
      $client = Client::query()
        ->select('admin_fee_flat')
        ->where('client_id', $clientId)
        ->first();
      if ($client && is_numeric($client->admin_fee_flat)) {
        $fee = (int) $client->admin_fee_flat;
      }
    }

    // 2) fallback dari config per client
    if ($fee === null) {
      $map = config('pay.admin_fee_flat_per_client', []);
      if (array_key_exists($clientId, $map)) {
        $fee = (int) $map[$clientId];
      }
    }

    // 3) default
    if ($fee === null) {
      $fee = (int) config('pay.admin_fee_flat_default', 0);
    }

    return max(0, $fee);
  }

  /**
   * Hitung net untuk client (gross - admin_fee_flat)
   */
  public static function netForClient(int $gross, ?string $clientId): int
  {
    $fee = self::forClient($clientId);
    return max(0, $gross - $fee);
  }
}
