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
  // Controller: ganti method ini seluruhnya
  public function routerHotspotLoginTest(Request $r, Client $client, MikrotikClient $mt)
  {
    // Minimal: username/password; lalu EITHER client_ip OR client_mac
    $data = $r->validate([
      'username'   => ['required','string','max:60'],
      'password'   => ['required','string','max:120'],
      'client_ip'  => ['nullable','ip','required_without:client_mac'],
      'client_mac' => ['nullable','string','max:32','required_without:client_ip'],
    ], [
      'username.required'            => 'Username wajib diisi.',
      'password.required'            => 'Password wajib diisi.',
      'client_ip.required_without'   => 'Isi IP klien atau MAC (salah satu).',
      'client_mac.required_without'  => 'Isi MAC atau IP klien (salah satu).',
      'client_ip.ip'                 => 'Format IP klien tidak valid.',
    ], [
      'client_ip'  => 'IP klien',
      'client_mac' => 'MAC klien',
    ]);

    try {
      $m  = $this->mtFor($mt, $client, $r);
      $ip = trim((string)($data['client_ip'] ?? ''));

      // Autodetect IP dari MAC kalau IP kosong
      if ($ip === '' && !empty($data['client_mac'])) {
        $ip = $this->detectClientIpFromRouter($m, $data['client_mac']);
        if ($ip === null) {
          return $this->jsonOrBack($r, false, 'Tidak bisa menemukan IP dari MAC. Isi IP klien atau pastikan perangkat muncul di /ip hotspot hosts.');
        }
      }

      // Login via API
      if (method_exists($m, 'hotspotActiveLogin')) {
        $m->hotspotActiveLogin($ip, $data['username'], $data['password'], $data['client_mac'] ?: null);
      } else {
        $params = ['ip'=>$ip,'user'=>$data['username'],'password'=>$data['password']];
        if (!empty($data['client_mac'])) $params['mac-address'] = $data['client_mac'];
        $m->raw('/ip/hotspot/active/login', $params);
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

  // Helper baru (letakkan di bawah helper lain)
  private function detectClientIpFromRouter(MikrotikClient $m, ?string $mac): ?string
  {
    try {
      // 1) Kalau ada MAC → langsung cari hostnya
      if ($mac) {
        $rows = $m->raw('/ip/hotspot/host/print', ['mac-address' => $mac]);
        if (is_array($rows) && !empty($rows[0]['address'])) {
          return $rows[0]['address'];
        }
      }

      // 2) Tanpa MAC: kalau hanya ada SATU host terdeteksi, pakai IP-nya (best-effort)
      $hosts = $m->raw('/ip/hotspot/host/print');
      if (is_array($hosts) && count($hosts) === 1) {
        return $hosts[0]['address'] ?? null;
      }

      // (opsional) bisa difilter "authorized"==false, tapi banyak variasi; kita simple saja
    } catch (\Throwable $e) {
      // diamkan → return null
    }
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
