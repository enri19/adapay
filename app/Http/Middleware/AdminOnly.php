<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AdminOnly
{
  public function handle($request, Closure $next)
  {
    if (!Auth::check()) {
      return redirect()->route('admin.login')->with('error','Silakan login admin.');
    }
    return $next($request);
  }
}
