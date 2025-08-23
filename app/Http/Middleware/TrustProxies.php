<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
  // TRUST SEMUA PROXY (Cloudflare/Ngrok/Nginx reverse proxy)
  protected $proxies = '*';

  // Pakai semua header X-Forwarded-* termasuk Host
  protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
