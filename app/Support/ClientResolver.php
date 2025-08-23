<?php

namespace App\Support;

use Illuminate\Http\Request;

class ClientResolver
{
  public static function resolve(Request $r): string
  {
    // 0) Header (opsional, kalau ada proxy)
    $cid = self::san($r->header('X-Client-ID'));
    if ($cid) return $cid;

    // 1) Query ?client=C1
    $cid = self::san($r->query('client'));
    if ($cid) return $cid;

    // 2) Subdomain c1.pay.example.com -> C1
    $host = $r->getHost(); // sudah respect trusted proxies kalau dikonfigurasi
    if (substr_count($host, '.') >= 2) {
      $first = explode('.', $host)[0] ?? '';
      $cid = self::san($first);
      if ($cid) return $cid;
    }

    // 3) Path segment /c/c1/...
    $path = trim($r->path(), '/'); // e.g. "c/c1/hotspot"
    $parts = explode('/', $path);
    if (count($parts) >= 2 && strtolower($parts[0]) === 'c') {
      $cid = self::san($parts[1]);
      if ($cid) return $cid;
    }

    // fallback
    return 'DEFAULT';
  }

  public static function san(?string $v): ?string
  {
    if ($v === null) return null;
    $v = strtoupper(preg_replace('/[^A-Z0-9]/', '', $v));
    if ($v === '') return null;
    return substr($v, 0, 12);
  }
}
