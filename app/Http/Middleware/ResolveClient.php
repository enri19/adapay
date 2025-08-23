<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;

class ResolveClient
{
  private function sanitize($v): ?string {
    $v = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$v));
    return $v ?: null;
  }

  public function handle(Request $request, Closure $next)
  {
    $cid = null;

    // 1) ?client=...
    $cid = $this->sanitize($request->query('client'));

    // 2) Subdomain <slug>.domain.tld
    if (!$cid) {
      $host = $request->getHost(); // ex: c1.hotspot.example.com
      // ambil label pertama
      if (substr_count($host, '.') >= 2) {
        $first = explode('.', $host)[0];
        $cid = $this->sanitize($first);
      }
    }

    // 3) Header X-Client-ID
    if (!$cid) {
      $cid = $this->sanitize($request->header('X-Client-ID'));
    }

    // 4) Session fallback
    if (!$cid) {
      $cid = $this->sanitize($request->session()->get('client_id')) ?: 'DEFAULT';
    }

    // Validasi ke DB; kalau tidak ada, tetap diset tapi tandai default saat checkout
    $exists = Client::where('client_id', $cid)->orWhere('slug', $cid)->first();
    $final = $exists ? $exists->client_id : 'DEFAULT';

    // simpan ke session & share ke view
    $request->session()->put('client_id', $final);
    view()->share('resolvedClientId', $final);

    return $next($request);
  }
}
