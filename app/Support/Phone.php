<?php

namespace App\Support;

final class Phone
{
  /**
   * Normalisasi nomor WA Indonesia menjadi 62xxxxxxxxx.
   * Menerima input seperti: "0812xxxx", "62xxxx", "+62xxxx", beserta karakter non-digit.
   *
   * @param  string|null  $raw
   * @return string|null
   */
  public static function normalizePhone($raw)
  {
    if (!$raw) {
      return null;
    }

    $s = trim((string) $raw);
    $plus62 = (strpos($s, '+62') === 0);
    $digits = preg_replace('/\D+/', '', $s);
    if ($digits === null) {
      $digits = '';
    }

    if ($plus62 && strpos($digits, '62') === 0) {
      $norm = '62' . substr($digits, 2);
    } elseif (strpos($digits, '62') === 0) {
      $norm = $digits;
    } elseif (strpos($digits, '0') === 0) {
      $norm = '62' . substr($digits, 1);
    } else {
      $norm = $digits;
    }

    // Valid longgar: harus mulai 62 + digit berikutnya "8", total panjang 10–15 digit.
    // Jika tidak match, tetap kembalikan $norm supaya bisa dilihat/log di sisi server.
    if (preg_match('/^62[8][0-9]{8,13}$/', $norm)) {
      return $norm;
    }

    return $norm;
  }
}
