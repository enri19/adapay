<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use App\Services\Mikrotik\MikrotikClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AdminClientController extends Controller
{
  // Tambahkan middleware auth kalau perlu
  // public function __construct(){ $this->middleware('auth'); }

  public function index() {
    $clients = Client::orderBy('is_active','desc')->orderBy('client_id')->get();
    return view('admin.clients.index', compact('clients'));
  }

  public function create() {
    $client = new Client(['router_port'=>8728, 'default_profile'=>'default', 'enable_push'=>false, 'is_active'=>true]);
    return view('admin.clients.form', compact('client'));
  }

  public function store(Request $r) {
    $data = $this->validateData($r);
    Client::create($data);
    return redirect()->route('admin.clients.index')->with('ok','Client dibuat');
  }

  public function edit(Client $client) {
    return view('admin.clients.form', compact('client'));
  }

  public function update(Request $r, Client $client) {
    $data = $this->validateData($r, $client->id);
    $client->update($data);
    return redirect()->route('admin.clients.index')->with('ok','Client diupdate');
  }

  public function destroy(Client $client) {
    $client->delete();
    return back()->with('ok','Client dihapus');
  }

  private function validateData(Request $r, $ignoreId = null): array {
    $uid = 'unique:clients,client_id';
    if ($ignoreId) $uid .= ',' . $ignoreId;
    return $r->validate([
      'client_id'       => ['required','max:12','regex:/^[A-Za-z0-9]+$/',$uid],
      'name'            => ['required','max:100'],
      'slug'            => ['required','max:100'],
      'portal_domain'   => ['max:100'],
      'router_host'     => ['nullable','max:255'],
      'router_port'     => ['nullable','integer','min:1','max:65535'],
      'router_user'     => ['nullable','max:100'],
      'router_pass'     => ['nullable','max:255'],
      'default_profile' => ['required','max:100'],
      'auth_mode'       => 'required|in:code,userpass',
      'hotspot_portal'  => ['nullable', 'string', 'max:255'],
      'enable_push'     => ['sometimes','boolean'],
      'is_active'       => ['sometimes','boolean'],
    ]);
  }

  /** Halaman tools: kumpulkan profile & server dari router (jika bisa) */
  public function tools(Request $r, Client $client, MikrotikClient $mt)
  {
    $profiles = [];
    $servers  = [];

    try {
      $mtClient = $this->configureMtForClient($mt, $client);

      // ambil profiles
      if (method_exists($mtClient, 'listHotspotProfiles')) {
        $profiles = (array) $mtClient->listHotspotProfiles();          // ['default','1M','2M',...]
      } elseif (method_exists($mtClient, 'raw')) {
        $rows = $mtClient->raw('/ip/hotspot/user/profile/print');      // bebas sesuai driver
        $profiles = collect($rows)->pluck('name')->filter()->values()->all();
      }

      // ambil servers
      if (method_exists($mtClient, 'listHotspotServers')) {
        $servers = (array) $mtClient->listHotspotServers();            // ['hotspot1','hotspot2',...]
      } elseif (method_exists($mtClient, 'raw')) {
        $rows = $mtClient->raw('/ip/hotspot/print');
        $servers = collect($rows)->pluck('name')->filter()->values()->all();
      }
    } catch (\Throwable $e) {
      // biarkan kosong; tampilkan info di view
      $r->session()->flash('error', 'Tidak bisa membaca profil/server: '.$e->getMessage());
    }

    // default ganjel
    if (empty($profiles)) $profiles = [ $client->default_profile ?: 'default' ];
    if (empty($servers))  $servers  = [];

    return view('admin.clients.tools', compact('client','profiles','servers'));
  }

  /** Test koneksi router: ping + identity/resource ringkas jika ada */
  public function routerTest(Request $r, Client $client, MikrotikClient $mt)
  {
    try {
      $mtClient = $this->configureMtForClient($mt, $client);
      if (method_exists($mtClient, 'ping')) $mtClient->ping();

      $identity = $board = $version = $uptime = null;
      if (method_exists($mtClient, 'getSystemInfo')) {
        $info = (array) $mtClient->getSystemInfo();
        $identity = $info['identity'] ?? null;
        $board    = $info['board'] ?? null;
        $version  = $info['version'] ?? null;
        $uptime   = $info['uptime'] ?? null;
      }

      $msg = $identity || $board || $version
        ? sprintf('Tersambung: %s%s%s%s',
            $identity ?: 'router',
            $board   ? " ($board)" : '',
            $version ? " v$version" : '',
            $uptime  ? ", uptime $uptime" : ''
          )
        : 'Tersambung ke router.';

      if ($r->ajax() || $r->wantsJson() || $r->boolean('ajax')) {
        return response()->json(['ok'=>true,'message'=>$msg]);
      }
      return back()->with('ok',$msg);
    } catch (\Throwable $e) {
      if ($r->ajax() || $r->wantsJson() || $r->boolean('ajax')) {
        return response()->json(['ok'=>false,'message'=>'Gagal konek: '.$e->getMessage()], 500);
      }
      return back()->with('error','Gagal konek: '.$e->getMessage());
    }
  }

  /** Buat/overwrite user hotspot test */
  public function routerHotspotTestUser(Request $r, Client $client, MikrotikClient $mt)
  {
    $data = $r->validate([
      'name'     => ['nullable','string','max:60'],
      'password' => ['nullable','string','max:60'],
      'profile'  => ['nullable','string','max:120'],
      'server'   => ['nullable','string','max:120'],
      'limit'    => ['nullable','string','max:20'],
      'mode'     => ['nullable','in:userpass,code'],
    ]);

    $mode = strtolower((string) ($data['mode'] ?? $client->auth_mode ?? 'userpass'));
    if (!in_array($mode, ['userpass','code'], true)) $mode = 'userpass';

    $suffix  = now()->format('ymdHi');
    $name    = trim((string)($data['name'] ?? 'test-'.$suffix));
    if ($mode === 'code') {
      $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
      $code=''; for ($i=0;$i<8;$i++) $code .= $alphabet[random_int(0, strlen($alphabet)-1)];
      $password = $data['password'] ?? $code;
      $name     = $data['name']     ?? $code;
    } else {
      $password = $data['password'] ?? 'pass-'.$suffix;
    }

    $profile = (string) ($data['profile'] ?? ($client->default_profile ?: 'default'));
    $server  = (string) ($data['server'] ?? '');
    $limit   = (string) ($data['limit'] ?? '10m');

    try {
      $mtClient = $this->configureMtForClient($mt, $client);
      $comment  = 'created-by-admin-test '.now()->format('Y-m-d H:i:s');
      $mtClient->createHotspotUser($name, $password, $profile, $comment, $limit, $server ?: null);

      $msg = "User test dibuat: $name / $password (profile: $profile, limit: $limit)";
      if ($r->ajax() || $r->wantsJson() || $r->boolean('ajax')) {
        return response()->json(['ok'=>true,'message'=>$msg]);
      }
      return back()->with('ok',$msg);
    } catch (\Throwable $e) {
      if ($r->ajax() || $r->wantsJson() || $r->boolean('ajax')) {
        return response()->json(['ok'=>false,'message'=>'Gagal membuat user hotspot: '.$e->getMessage()], 500);
      }
      return back()->with('error','Gagal membuat user hotspot: '.$e->getMessage());
    }
  }

  /**
   * Test login ke portal hotspot (best effort):
   * - gunakan client->hotspot_portal (kalau ada)
   * - kirim POST host, port, username dan password (form field standar MikroTik)
   * - catatan: hanya bekerja bila server aplikasi bisa menjangkau portal tersebut
   */
  public function routerHotspotLoginTest(Request $r, Client $client)
  {
    $data = $r->validate([
      'username' => ['required','string','max:60'],
      'password' => ['required','string','max:120'],
      'portal'   => ['nullable','string'],          // opsional, tapi kita abaikan kalau host/port ada
      'web_port' => ['nullable','integer','min:1','max:65535'],
      'https'    => ['nullable'],                   // "on" / "1"
      'ajax'     => ['nullable'],
    ]);

    $username   = (string) $data['username'];
    $password   = (string) $data['password'];
    $routerHost = trim((string) ($client->router_host ?? ''));
    $webPort    = isset($data['web_port']) ? (int)$data['web_port'] : null;
    $useHttps   = $r->boolean('https');

    // SUSUN kandidat URL login:
    $candidates = [];

    // 0) Kalau user ngisi portal, tetap coba paling pertama
    if (!empty($data['portal'])) {
      $p = trim((string)$data['portal']);
      if ($p !== '') $candidates[] = rtrim($p, '/');
    }

    // 1) Pakai router_host + web_port dari form
    if ($routerHost !== '') {
      if ($webPort) {
        $scheme = $useHttps ? 'https' : 'http';
        $candidates[] = "{$scheme}://{$routerHost}:{$webPort}/login";
      } else {
        // 2) Coba port umum hotspot & web
        foreach ([['http',64872],['http',80],['http',8080],['https',64873],['https',443]] as $p) {
          $candidates[] = "{$p[0]}://{$routerHost}:{$p[1]}/login";
        }
      }
    }

    if (empty($candidates)) {
      return $this->loginResp($r, false, 'Router host kosong; isi host di data Client.');
    }

    $http = Http::withOptions([
      'timeout' => 10,
      'verify'  => false,           // hotspot/ssl mikrotik sering self-signed
      'allow_redirects' => true,
    ]);

    foreach ($candidates as $loginPage) {
      try {
        // 1) GET login page untuk ambil link-login-only, dst, CHAP
        $res  = $http->get($loginPage);
        $html = (string) $res->body();

        $linkLoginOnly = $this->findMatch($html, '/name=[\'"]link-login-only[\'"][^>]*value=[\'"]([^\'"]+)/i')
                          ?: $this->findMatch($html, '/id=[\'"]link-login-only[\'"][^>]*href=[\'"]([^\'"]+)/i');
        $dst           = $this->findMatch($html, '/name=[\'"]dst[\'"][^>]*value=[\'"]([^\'"]*)/i')
                          ?: $this->findMatch($html, '/name=[\'"]link-orig[\'"][^>]*value=[\'"]([^\'"]*)/i');

        $chapId        = $this->findMatch($html, '/name=[\'"]chap-id[\'"][^>]*value=[\'"]([^\'"]+)/i');
        $chapChallenge = $this->findMatch($html, '/name=[\'"]chap-challenge[\'"][^>]*value=[\'"]([^\'"]+)/i');

        $postUrl = $linkLoginOnly ? $this->resolveUrl($loginPage, $linkLoginOnly) : $loginPage;

        // 2) Siapkan payload (CHAP-aware)
        $payload = [
          'username' => $username,
          'dst'      => $dst ?: '',
          'popup'    => 'true',
        ];
        if ($chapId && $chapChallenge) {
          $payload['password'] = md5(chr(hexdec($chapId)).$password.hex2bin($chapChallenge));
        } else {
          $payload['password'] = $password;
        }

        // 3) POST login
        $post = $http->asForm()->withHeaders(['Referer' => $loginPage])->post($postUrl, $payload);
        $body = (string) $post->body();
        $loc  = $post->header('Location');
        $ok   = false;

        if ($post->status() >= 300 && $post->status() < 400 && $loc) {
          $ok = Str::contains(Str::lower($loc), ['status','logout','login-ok','success']);
        }
        if (!$ok) {
          $ok = Str::contains(Str::lower($body), ['logout','you are logged in','login-ok']);
        }

        if ($ok) {
          return $this->loginResp($r, true, 'Login HOTSPOT OK via ' . $postUrl);
        }

        // kalau gagal, coba kandidat berikutnya
      } catch (\Throwable $e) {
        // lanjut kandidat berikutnya
      }
    }

    return $this->loginResp($r, false,
      'Gagal menguji login. Server tidak bisa menjangkau hotspot di host/port yang dicoba atau respon tidak dikenali.'
    );
  }

  private function loginResp(Request $r, bool $ok, string $msg)
  {
    if ($r->ajax() || $r->wantsJson() || $r->boolean('ajax')) {
      return response()->json(['ok'=>$ok,'message'=>$msg], $ok ? 200 : 422);
    }
    return $ok ? back()->with('ok',$msg) : back()->with('error',$msg);
  }

  private function findMatch(string $html, string $regex): ?string
  {
    if (preg_match($regex, $html, $m)) {
      return $m[1] ?? null;
    }
    return null;
  }

  private function resolveUrl(string $base, string $rel): string
  {
    if (preg_match('#^https?://#i', $rel)) return $rel;
    $u = parse_url($base);
    $scheme = $u['scheme'] ?? 'http';
    $host   = $u['host']   ?? '';
    $port   = isset($u['port']) ? ':'.$u['port'] : '';
    if (strpos($rel, '/') === 0) return "{$scheme}://{$host}{$port}{$rel}";
    $path = isset($u['path']) ? rtrim(dirname($u['path']),'/') : '';
    return "{$scheme}://{$host}{$port}{$path}/{$rel}";
  }

  private function guessLoginUrl(string $portal): string
  {
    // jika portal sudah /login → pakai itu; else tambahkan /login
    if (Str::endsWith(parse_url($portal, PHP_URL_PATH) ?? '', '/login')) {
      return $portal;
    }
    // buat absolut
    $parts = parse_url($portal);
    $base  = ($parts['scheme'] ?? 'http').'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
    return rtrim($base.'/'.ltrim($parts['path'] ?? '', '/'), '/').'/login';
  }

  private function configureMtForClient(MikrotikClient $mt, Client $client): MikrotikClient
  {
    $router = [
      'host' => (string) $client->router_host,
      'port' => (int)   ($client->router_port ?: 8728),
      'user' => (string) $client->router_user,
      'pass' => (string) $client->router_pass,
    ];

    if (empty($router['host']) || empty($router['user']) || empty($router['pass'])) {
      throw new \RuntimeException('Konfigurasi router belum lengkap (host/user/pass).');
    }

    if (method_exists($mt, 'withConfig')) {
      $new = $mt->withConfig($router);
      if ($new instanceof MikrotikClient) return $new;
    }
    if (method_exists($mt, 'connect')) {
      $mt->connect($router['host'], $router['port'], $router['user'], $router['pass']);
      return $mt;
    }
    if (method_exists(app(), 'makeWith')) {
      return app()->makeWith(MikrotikClient::class, ['config' => $router]);
    }
    return $mt;
  }
}
