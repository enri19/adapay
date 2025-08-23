<?php

namespace App\Support;

use Illuminate\Support\Str;

class OrderId
{
  public static function sanitizeClient(?string $v): string
  {
    $v = strtoupper((string)$v);
    $v = preg_replace('/[^A-Z0-9]/', '', $v);
    return $v ?: 'DEFAULT';
  }

  public static function make($client): string {
    $cid = self::sanitizeClient($client);
    return 'ORD-'.$cid.'-'.now()->format('Ymd-His').'-'.strtoupper(Str::random(6));
    // contoh: ORD-C1-20250823-021530-ABC123 (<= 50 char limit Midtrans, aman)
  }

  public static function client(string $orderId): ?string {
    if (preg_match('/^ORD\-([A-Z0-9]{1,12})\-/i', $orderId, $m)) return strtoupper($m[1]);
    return null;
  }
}
