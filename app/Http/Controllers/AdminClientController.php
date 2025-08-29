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

      try {
          $m = $this->mtFor($mt, $client);

          // Profil
          if (method_exists($m, 'listHotspotProfiles')) {
              $profiles = (array) $m->listHotspotProfiles();
          } else {
              $rows = $m->raw('/ip/hotspot/user/profile/print');
              $profiles = collect($rows)->pluck('name')->filter()->values()->all();
          }

          // Server
          if (method_exists($m, 'listHotspotServers')) {
              $servers = (array) $m->listHotspotServers();
          } else {
              $rows = $m->raw('/ip/hotspot/print');
              $servers = collect($rows)->pluck('name')->filter()->values()->all();
          }
      } catch (\Throwable $e) {
          // biarkan kosong; info error tampil dengan flash di view jika mau
          $r->session()->flash('error', 'Tidak bisa membaca profil/server: '.$e->getMessage());
      }

      if (empty($profiles)) $profiles = [ $client->default_profile ?: 'default' ];

      return view('admin.clients.tools', compact('client','profiles','servers'));
  }

  /**
   * Test koneksi API: ping + info ringkas (JSON jika AJAX).
   */
  public function routerTest(Request $r, Client $client, MikrotikClient $mt)
  {
      try {
          $m = $this->mtFor($mt, $client);

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
          $m = $this->mtFor($mt, $client);
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
  public function routerHotspotLoginTest(Request $r, Client $client, MikrotikClient $mt)
  {
      // Metode default: api
      $method = strtolower((string)$r->input('method','api'));

      if ($method !== 'api') {
          return $this->jsonOrBack($r, false, 'Metode HTTP portal tidak didukung di server ini. Pilih metode API.');
      }

      $data = $r->validate([
          'username'   => ['required','string','max:60'],
          'password'   => ['required','string','max:120'],
          'client_ip'  => ['required','ip'],
          'client_mac' => ['nullable','string','max:32'],
      ]);

      try {
          $m = $this->mtFor($mt, $client);

          if (method_exists($m, 'hotspotActiveLogin')) {
              $m->hotspotActiveLogin($data['client_ip'], $data['username'], $data['password'], $data['client_mac'] ?: null);
          } else {
              // generic fallback
              $params = [
                  'ip'       => $data['client_ip'],
                  'user'     => $data['username'],
                  'password' => $data['password'],
              ];
              if (!empty($data['client_mac'])) $params['mac-address'] = $data['client_mac'];
              $m->raw('/ip/hotspot/active/login', $params);
          }

          return $this->jsonOrBack($r, true, 'Login HOTSPOT OK via API (active session dibuat).');
      } catch (\Throwable $e) {
          return $this->jsonOrBack($r, false, 'Login via API gagal: '.$e->getMessage());
      }
  }

  /* ===================== Helpers ===================== */

  /**
   * Build instance MikrotikClient yang sudah terkonfigurasi untuk client.
   */
  private function mtFor(MikrotikClient $mt, Client $client): MikrotikClient
  {
      $router = [
          'host' => (string) $client->router_host,
          'port' => (int)   ($client->router_port ?: 8728),
          'user' => (string) $client->router_user,
          'pass' => (string) $client->router_pass,
          // ssl akan diatur otomatis oleh withConfig() jika port 8729
      ];

      if (empty($router['host']) || empty($router['user']) || empty($router['pass'])) {
          throw new \RuntimeException('Konfigurasi router belum lengkap (host/user/pass).');
      }

      if (method_exists($mt, 'withConfig')) {
          $new = $mt->withConfig($router);
          return ($new instanceof MikrotikClient) ? $new : $mt;
      }

      // Jika implementasi lama pakai connect(), tinggal tambah di service & panggil di sini.
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
