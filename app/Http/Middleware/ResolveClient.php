<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ResolveClient
{
    private function norm(?string $v): ?string {
        $v = trim((string)$v);
        return $v !== '' ? $v : null;
    }

    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());              // ex: pay.adanih.info
        $labels = explode('.', $host);

        $client = null;

        // 1) PRIORITAS: exact domain match
        $client = Client::where('domain', $host)->first();

        // 2) Kalau belum ketemu: tebak slug dari label pertama (subdomain)
        if (!$client && count($labels) >= 3) {
            $slug = $this->norm($labels[0]);                  // ex: c1.example.com -> "c1"
            if ($slug) {
                $client = Client::where('slug', $slug)
                    ->orWhere('client_id', $slug)
                    ->first();
            }
        }

        // 3) Override via query/header jika disuplai
        if (!$client) {
            $hint = $this->norm($request->query('client')) ?: $this->norm($request->header('X-Client-ID'));
            if ($hint) {
                $client = Client::where('slug', $hint)
                    ->orWhere('client_id', $hint)
                    ->orWhere('id', (int)$hint)
                    ->first();
            }
        }

        // 4) Fallback session
        if (!$client) {
            $sid = (int) ($request->session()->get('client_id') ?? 0);
            if ($sid > 0) {
                $client = Client::find($sid);
            }
        }

        // Hasil akhir: ID numerik atau null
        $finalId = $client ? (int)$client->id : null;

        // simpan utk controller API & share ke Blade
        $request->attributes->set('client_id', $finalId);
        $request->session()->put('client_id', $finalId);
        View::share('resolvedClientId', $finalId);

        return $next($request);
    }
}
