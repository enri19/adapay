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
   * Halaman Tools: ambil daftar profile/server (best effort).
   */
  public function tools(Request $r, Client $client, MikrotikClient $mt)
  {
    $profiles = [];
    $servers  = [];
    $online   = false;

    try {
      $m = $this->mtFor($mt, $client);

      // Coba ping ringan; kalau gagal lempar ke catch
      if (method_exists($m, 'ping')) {
        $m->ping();
      } else {
        $m->raw('/system/identity/print');
      }
      $online = true;

      // Hanya kalau online → ambil daftar
      try {
        $profiles = method_exists($m,'listHotspotProfiles')
          ? (array) $m->listHotspotProfiles()
          : collect($m->raw('/ip/hotspot/user/profile/print'))->pluck('name')->filter()->values()->all();

        $servers = method_exists($m,'listHotspotServers')
          ? (array) $m->listHotspotServers()
          : collect($m->raw('/ip/hotspot/print'))->pluck('name')->filter()->values()->all();
      } catch (\Throwable $e) {
        // diamkan; form tetap bisa dipakai manual
      }
    } catch (\Throwable $e) {
      // offline: jangan flash error keras, cukup kasih indikator di view
      // $r->session()->flash('error','Router offline: '.$e->getMessage());
    }

    if (empty($profiles)) $profiles = [ $client->default_profile ?: 'default' ];

    return view('admin.clients.tools', compact('client','profiles','servers','online'));
  }

  /**
   * Test koneksi API: ping + info ringkas (JSON jika AJAX).
   */
  public function routerTest(Request $r, Client $client, MikrotikClient $mt)
  {
    try {
      $m = $this->mtFor($mt, $client, $r);

      if (method_exists($m, 'ping')) {
        $m->ping();
      } else {
        // fallback ringan
        $m->raw('/system/identity/print');
      }

      $info = method_exists($m, 'getSystemInfo') ? (array)$m->getSystemInfo() : [];
      $msg  = 'Tersambung ke router.';
      if (!empty($info)) {
        $msg = sprintf(
          'Tersambung: %s%s%s%s',
          $info['identity'] ?? 'router',
          !empty($info['board'])   ? ' ('.$info['board'].')'   : '',
          !empty($info['version']) ? ' v'.$info['version']     : '',
          !empty($info['uptime'])  ? ', uptime '.$info['uptime']: ''
        );
      }

      return $this->jsonOrBack($r, true, $msg);
    } catch (\Throwable $e) {
      return $this->jsonOrBack($r, false, 'Gagal konek: '.$e->getMessage());
    }
  }

  /**
   * Buat/overwrite user hotspot test (idempotent).
   */
  public function routerHotspotTestUser(Request $r, Client $client, MikrotikClient $mt)
  {
      $data = $r->validate([
          'name'     => ['nullable','string','max:60'],
          'password' => ['nullable','string','max:60'],
          'profile'  => ['nullable','string','max:120'],
          'limit'    => ['nullable','string','max:20'], // contoh: 10m, 1h
          'mode'     => ['nullable','in:userpass,code'],
      ]);

      // default dari client
      $mode    = strtolower((string)($data['mode'] ?? $client->auth_mode ?? 'userpass'));
      if (!in_array($mode, ['userpass','code'], true)) $mode = 'userpass';

      $suffix  = now()->format('ymdHi');
      $name    = trim((string)($data['name'] ?? 'test-'.$suffix));

      if ($mode === 'code') {
          $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
          $code=''; for($i=0;$i<8;$i++) $code .= $alphabet[random_int(0, strlen($alphabet)-1)];
          $password = $data['password'] ?? $code;
          $name     = $data['name']     ?? $code;
      } else {
          $password = $data['password'] ?? 'pass-'.$suffix;
      }

      $profile = (string) ($data['profile'] ?? ($client->default_profile ?: 'default'));
      $limit   = (string) ($data['limit'] ?? '10m');

      try {
          $m = $this->mtFor($mt, $client, $r);
          $comment = 'created-by-admin-test '.now()->format('Y-m-d H:i:s');

          // Signature 5 argumen (tanpa server) sesuai service kamu
          $m->createHotspotUser($name, $password, $profile, $comment, $limit);

          $msg = "User test dibuat: $name / $password (profile: $profile, limit: $limit)";
          return $this->jsonOrBack($r, true, $msg);
      } catch (\Throwable $e) {
          return $this->jsonOrBack($r, false, 'Gagal membuat user hotspot: '.$e->getMessage());
      }
  }

  /**
   * Test login hotspot via API (butuh IP klien; MAC opsional).
   */
  // Controller: routerHotspotLoginTest (final)
  public function routerHotspotLoginTest(Request $r, Client $client, MikrotikClient $mt)
  {
    // View cukup kirim username & password (tanpa IP/MAC)
    $data = $r->validate([
      'username' => ['required','string','max:60'],
      'password' => ['required','string','max:120'],
    ], [
      'username.required' => 'Username wajib diisi.',
      'password.required' => 'Password wajib diisi.',
    ]);

    try {
      $m = $this->mtFor($mt, $client, $r);

      // 1) auto-detect IP klien
      $ip = $this->detectAnyClientIp($m);
      if (!$ip) {
        return $this->jsonOrBack($r, false,
          'Tidak ada perangkat (host) terdeteksi. Sambungkan perangkat ke SSID hotspot lalu coba lagi.');
      }

      // 2) login via API
      if (method_exists($m, 'hotspotActiveLogin')) {
        $m->hotspotActiveLogin($ip, $data['username'], $data['password'], null);
      } else {
        $m->raw('/ip/hotspot/active/login', [
          'ip'       => $ip,
          'user'     => $data['username'],
          'password' => $data['password'],
        ]);
      }

      return $this->jsonOrBack($r, true, 'Login HOTSPOT berhasil (session aktif dibuat).');
    } catch (\Throwable $e) {
      $msg = $e->getMessage() ?: 'Gagal login.';
      if (stripos($msg, 'invalid user name or password') !== false) {
        $msg = 'Username atau password tidak valid.';
      } elseif (stripos($msg, 'no route to host') !== false) {
        $msg = 'Tidak dapat terhubung ke router (No route to host). Cek routing/firewall.';
      }
      return $this->jsonOrBack($r, false, $msg);
    }
  }

  // Helper: cari IP klien dari beberapa sumber RouterOS
  private function detectAnyClientIp(MikrotikClient $m): ?string
  {
    // A. Hotspot host (kalau hotspot aktif)
    try {
      $rows = $m->raw('/ip/hotspot/host/print');
      if (is_array($rows)) {
        foreach ($rows as $r) {
          $ip = $r['address'] ?? null;
          if (!empty($ip)) return $ip;
        }
      }
    } catch (\Throwable $e) { /* menu mungkin nggak ada */ }

    // B. ARP (IP dinamis yang terlihat di bridge/wlan)
    try {
      $rows = $m->raw('/ip/arp/print');
      if (is_array($rows)) {
        foreach ($rows as $r) {
          $ip  = $r['address'] ?? null;
          $dyn = ($r['dynamic'] ?? 'false') === 'true';
          // ambil entri dinamis pertama yang punya IP
          if ($ip && $dyn && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
      }
    } catch (\Throwable $e) {}

    // C. DHCP leases (kalau ada dhcp-server)
    try {
      $rows = $m->raw('/ip/dhcp-server/lease/print');
      if (is_array($rows)) {
        foreach ($rows as $r) {
          if (($r['status'] ?? '') === 'bound' && !empty($r['address'])) {
            return $r['address'];
          }
        }
      }
    } catch (\Throwable $e) {}

    return null;
  }


  /* ===================== Helpers ===================== */

  /**
   * Build instance MikrotikClient yang sudah terkonfigurasi untuk client.
   */
  private function mtFor(MikrotikClient $mt, Client $client, ?Request $r = null): MikrotikClient
  {
      // nilai default dari DB
      $router = [
          'host' => (string) $client->router_host,
          'port' => (int)   ($client->router_port ?: 8728),
          'user' => (string) $client->router_user,
          'pass' => (string) $client->router_pass,
      ];

      // override dari request (jika dikirim dari view)
      if ($r) {
          $ovhHost = trim((string) $r->input('router_host',''));
          $ovhPort = $r->input('router_port');
          if ($ovhHost !== '') $router['host'] = $ovhHost;
          if ($ovhPort !== null && $ovhPort !== '') $router['port'] = (int) $ovhPort;
      }

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

  /**
   * Helper respons: JSON untuk AJAX, flash redirect untuk normal.
   */
  private function jsonOrBack(Request $r, bool $ok, string $msg)
  {
      if ($r->ajax() || $r->wantsJson() || $r->boolean('ajax')) {
          return response()->json(['ok'=>$ok,'message'=>$msg], $ok ? 200 : 422);
      }
      return $ok ? back()->with('ok',$msg) : back()->with('error',$msg);
  }
}
