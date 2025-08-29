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
   * - kirim POST username/password (form field standar MikroTik)
   * - catatan: hanya bekerja bila server aplikasi bisa menjangkau portal tersebut
   */
  public function routerHotspotLoginTest(Request $r, Client $client)
  {
    $data = $r->validate([
      'username' => ['required','string','max:60'],
      'password' => ['required','string','max:120'],
      'portal'   => ['nullable','string'], // biarkan url tidak tervalidasi ketat (bisa IP/HTTP lokal)
      'ajax'     => ['nullable'],
    ]);

    $username = (string) $data['username'];
    $password = (string) $data['password'];
    $portal   = trim((string) ($data['portal'] ?: $client->hotspot_portal ?: ''));

    if ($portal === '') {
      return $this->loginResp($r, false, 'Portal hotspot belum diisi (client.hotspot_portal).');
    }

    try {
      // 1) GET portal untuk dapatkan link-login-only, dst, & CHAP
      $http = Http::withOptions([
        'timeout' => 10,
        'verify'  => false,       // mikrotik sering pakai cert self-signed
        'allow_redirects' => true,
      ]);

      $resp = $http->get($portal);
      $html = (string) $resp->body();

      // Extract values
      $linkLoginOnly = $this->findMatch($html, '/link-login-only[^"\']*["\']([^"\']+)/i');
      $dst           = $this->findMatch($html, '/name=[\'"]dst[\'"][^>]*value=[\'"]([^\'"]*)/i')
                        ?? $this->findMatch($html, '/link-orig[^"\']*["\']([^"\']+)/i');

      $chapId        = $this->findMatch($html, '/name=[\'"]chap-id[\'"][^>]*value=[\'"]([^\'"]+)/i');
      $chapChallenge = $this->findMatch($html, '/name=[\'"]chap-challenge[\'"][^>]*value=[\'"]([^\'"]+)/i');

      // Tentukan endpoint login
      $loginUrl = $linkLoginOnly ?: $this->guessLoginUrl($portal);

      // 2) Build payload: CHAP → hash md5(id + pass + challenge), else kirim plain
      $payload = [
        'username' => $username,
        'dst'      => $dst ?: '',
        'popup'    => 'true',
      ];

      if ($chapId && $chapChallenge) {
        $passHashed = md5(chr(hexdec($chapId)).$password.hex2bin($chapChallenge));
        // Banyak template MikroTik mengirim field "password" berisi hash MD5 (bukan "response")
        $payload['password'] = $passHashed;
      } else {
        $payload['password'] = $password;
      }

      // 3) POST login
      $post = $http->asForm()->withHeaders(['Referer'=>$portal])->post($loginUrl, $payload);
      $body = (string) $post->body();
      $loc  = $post->header('Location');

      // 4) Heuristik sukses
      $ok = false;
      if ($post->status() >= 300 && $post->status() < 400 && $loc) {
        $ok = Str::contains($loc, ['/status', 'status', 'success']);
      }
      if (!$ok) {
        $ok = Str::contains(Str::lower($body), ['logout', 'logged in', 'login-ok']);
      }

      if ($ok) {
        return $this->loginResp($r, true, 'Login HOTSPOT OK (respon portal menunjukkan sukses).');
      }

      $snippet = mb_substr(trim(strip_tags($body ?: '')), 0, 200);
      return $this->loginResp($r, false, 'Login HOTSPOT gagal / respon tidak dikenali. Potongan: '.$snippet);
    } catch (\Throwable $e) {
      return $this->loginResp($r, false, 'Tidak bisa menghubungi portal: '.$e->getMessage());
    }
  }

  // helper kecil
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

}
