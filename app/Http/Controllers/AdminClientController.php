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

  /**
   * Tools page (hanya coba ping + ambil profile/server kalau bisa)
   */
  public function tools(Request $r, Client $client, MikrotikClient $mt)
  {
    $profiles = [];
    $servers  = [];
    $online   = false;

    try {
      $m = $this->mtFor($mt, $client);

      // ping ringan
      if (method_exists($m, 'ping')) $m->ping();
      else $m->raw('/system/identity/print');

      $online = true;

      // best-effort
      try {
        $profiles = method_exists($m,'listHotspotProfiles')
          ? (array) $m->listHotspotProfiles()
          : collect($m->raw('/ip/hotspot/user/profile/print'))->pluck('name')->filter()->values()->all();

        $servers = method_exists($m,'listHotspotServers')
          ? (array) $m->listHotspotServers()
          : collect($m->raw('/ip/hotspot/print'))->pluck('name')->filter()->values()->all();
      } catch (\Throwable $e) {
        // biarkan kosong
      }
    } catch (\Throwable $e) {
      // offline → tampil indikator di view saja
    }

    if (empty($profiles)) $profiles = [ $client->default_profile ?: 'default' ];

    return view('admin.clients.tools', compact('client','profiles','servers','online'));
  }

  /**
   * Test koneksi router
   */
  public function routerTest(Request $r, Client $client, MikrotikClient $mt)
  {
    try {
      $m = $this->mtFor($mt, $client);
      if (method_exists($m,'ping')) $m->ping(); else $m->raw('/system/identity/print');
      $info = method_exists($m,'getSystemInfo') ? (array)$m->getSystemInfo() : [];
      $msg  = 'Tersambung ke router.';
      if (!empty($info)) {
        $msg = sprintf('Tersambung: %s%s%s%s',
          $info['identity'] ?? 'router',
          !empty($info['board'])   ? ' ('.$info['board'].')'   : '',
          !empty($info['version']) ? ' v'.$info['version']     : '',
          !empty($info['uptime'])  ? ', uptime '.$info['uptime']: ''
        );
      }
      return $this->jsonOrBack($r, true, $msg);
    } catch (\Throwable $e) {
      $msg = $e->getMessage() ?: 'Gagal konek.';
      if (stripos($msg, 'invalid user name or password') !== false) {
        $msg = 'User/Password API router salah. Pastikan memakai **/user** (system user), bukan hotspot user.';
      }
      return $this->jsonOrBack($r, false, 'Gagal konek: '.$msg);
    }
  }

  /**
   * Buat/overwrite user hotspot (test)
   */
  public function routerHotspotTestUser(Request $r, Client $client, MikrotikClient $mt)
  {
    $data = $r->validate([
      'name'     => ['nullable','string','max:60'],
      'password' => ['nullable','string','max:60'],
      'profile'  => ['nullable','string','max:120'],
      'limit'    => ['nullable','string','max:20'], // contoh: 10m, 30m, 1h
      'mode'     => ['nullable','in:userpass,code'],
    ]);

    // === mode sesuai referensi: default ke 'code' ===
    $mode = strtolower((string)($data['mode'] ?? $client->auth_mode ?? 'code'));
    if (!in_array($mode, ['code','userpass'], true)) $mode = 'code';

    // === generate kredensial sesuai referensi (ALL UPPERCASE) ===
    $username = null;
    $password = null;

    if ($mode === 'userpass') {
      // jika user isi, pakai & UPPERCASE; kalau kosong → generate
      $username = strtoupper(trim((string)($data['name'] ?? '')));
      $password = strtoupper(trim((string)($data['password'] ?? '')));
      if ($username === '') {
        $username = 'HV-' . strtoupper(\Illuminate\Support\Str::random(6));
      }
      if ($password === '') {
        $password = strtoupper(\Illuminate\Support\Str::random(8));
      }
    } else {
      // mode "code": username == password == KODE (tanpa karakter rancu)
      $provided = strtoupper(trim((string)($data['name'] ?? '')));
      if ($provided === '') {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no I,O,0,1
        $code = '';
        for ($i = 0; $i < 8; $i++) {
          $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $provided = $code;
      }
      $username = $provided;
      // kalau user isi password, abaikan → tetap samakan dengan username (sesuai referensi)
      $password = $provided;
    }

    $profile = (string) ($data['profile'] ?? ($client->default_profile ?: 'default'));
    $limit   = (string) ($data['limit'] ?? '10m');

    try {
      $m = $this->mtFor($mt, $client);
      $comment = 'TEST user via admin.tools ' . now()->format('Y-m-d H:i:s');

      // Signature service kamu: (name, pass, profile, comment, limitUptime)
      $m->createHotspotUser($username, $password, $profile, $comment, $limit);

      $msg = "User test dibuat: {$username} / {$password} (profile: {$profile}, limit: {$limit})";
      return $this->jsonOrBack($r, true, $msg);
    } catch (\Throwable $e) {
      return $this->jsonOrBack($r, false, 'Gagal membuat user hotspot: ' . $e->getMessage());
    }
  }

  /**
   * Router Test Login
   */
  public function routerHotspotLoginTest(Request $r, Client $client, \App\Services\Mikrotik\MikrotikClient $mt)
  {
    // View cukup kirim username & password; MAC opsional
    $data = $r->validate([
      'username' => ['required','string','max:60'],
      'password' => ['required','string','max:120'],
      'mac'      => ['nullable','string','max:32'],
    ], [
      'username.required' => 'Username wajib diisi.',
      'password.required' => 'Password wajib diisi.',
    ]);

    try {
      $m  = $this->mtFor($mt, $client);

      // Cari IP klien otomatis dari router (host → ARP → DHCP)
      $ip = $this->detectAnyClientIp($m, $data['mac'] ?? null);
      if (!$ip) {
        return $this->jsonOrBack($r, false, 'Tidak ada perangkat terdeteksi di router. Sambungkan perangkat ke SSID hotspot lalu coba lagi.');
      }

      // Login via API
      if (method_exists($m, 'hotspotActiveLogin')) {
        $m->hotspotActiveLogin($ip, $data['username'], $data['password'], $data['mac'] ?? null);
      } elseif (method_exists($m, 'raw')) {
        $params = ['ip'=>$ip,'user'=>$data['username'],'password'=>$data['password']];
        if (!empty($data['mac'])) $params['mac-address'] = $data['mac'];
        $m->raw('/ip/hotspot/active/login', $params);
      } else {
        throw new \RuntimeException('Driver Mikrotik belum mendukung login (hotspotActiveLogin/raw).');
      }

      return $this->jsonOrBack($r, true, 'Login HOTSPOT berhasil (session aktif dibuat).');
    } catch (\Throwable $e) {
      $msg = $e->getMessage() ?: 'Gagal login.';
      if (stripos($msg, 'invalid user name or password') !== false) {
        $msg = 'Username atau password tidak valid.';
      } elseif (stripos($msg, 'no route to host') !== false) {
        $msg = 'Tidak dapat terhubung ke router (No route to host). Cek routing/VPN/firewall dari server aplikasi.';
      }
      return $this->jsonOrBack($r, false, $msg);
    }
  }

  /**
   * Cari IP klien dari router:
   *  - hotspot hosts (kalau ada)
   *  - ARP (dinamis)
   *  - DHCP leases (status bound)
   */
  private function detectAnyClientIp($m, ?string $mac): ?string
  {
    // A) /ip/hotspot/host
    try {
      if (method_exists($m, 'raw')) {
        $rows = $mac
          ? $m->raw('/ip/hotspot/host/print', ['mac-address'=>$mac])
          : $m->raw('/ip/hotspot/host/print');
        if (is_array($rows)) {
          foreach ($rows as $r) {
            $ip = $r['address'] ?? null;
            if (!empty($ip)) return $ip;
          }
        }
      }
    } catch (\Throwable $e) {}

    // B) /ip/arp
    try {
      if (method_exists($m, 'raw')) {
        $rows = $mac
          ? $m->raw('/ip/arp/print', ['mac-address'=>$mac])
          : $m->raw('/ip/arp/print');
        if (is_array($rows)) {
          foreach ($rows as $r) {
            $ip  = $r['address'] ?? null;
            $dyn = ($r['dynamic'] ?? 'false') === 'true';
            if ($ip && $dyn && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
          }
        }
      }
    } catch (\Throwable $e) {}

    // C) /ip/dhcp-server/lease
    try {
      if (method_exists($m, 'raw')) {
        $rows = $mac
          ? $m->raw('/ip/dhcp-server/lease/print', ['mac-address'=>$mac])
          : $m->raw('/ip/dhcp-server/lease/print');
        if (is_array($rows)) {
          foreach ($rows as $r) {
            if (($r['status'] ?? '') === 'bound' && !empty($r['address'])) {
              return $r['address'];
            }
          }
        }
      }
    } catch (\Throwable $e) {}

    return null;
  }

  /* ========== Helpers (DB only, tanpa override dari view) ========== */

  private function mtFor(MikrotikClient $mt, Client $client): MikrotikClient
  {
    $router = [
      'host' => (string) $client->router_host,
      'port' => (int)   ($client->router_port ?: 8728),
      'user' => (string) $client->router_user,
      'pass' => (string) $client->router_pass,
    ];

    if ($router['host'] === '' || $router['user'] === '' || $router['pass'] === '') {
      throw new \RuntimeException('Konfigurasi router belum lengkap (host/user/pass).');
    }

    if (method_exists($mt, 'withConfig')) {
      $new = $mt->withConfig($router);
      return ($new instanceof MikrotikClient) ? $new : $mt;
    }
    if (method_exists($mt, 'connect')) {
      $mt->connect($router['host'], $router['port'], $router['user'], $router['pass']);
      return $mt;
    }
    return $mt;
  }

  private function jsonOrBack(Request $r, bool $ok, string $msg)
  {
    if ($r->ajax() || $r->wantsJson() || $r->boolean('ajax')) {
      return response()->json(['ok'=>$ok,'message'=>$msg], $ok ? 200 : 422);
    }
    return $ok ? back()->with('ok',$msg) : back()->with('error',$msg);
  }
}
