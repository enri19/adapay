<?php

namespace App\Support;

final class Phone
{
  /**
   * Normalisasi nomor Indonesia ke format 62xxxxxxxxx sederhana.
   */
  public static function normalizeId(?string $raw): ?string
  {
    if (!$raw) {
      return null;
    }
    $p = preg_replace('/\D+/', '', $raw);
    if (strpos($p, '0') === 0) {
      $p = '62' . substr($p, 1);
    }
    return $p;
  }
}
