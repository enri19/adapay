<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\HotspotVoucher;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\ResolvesRoleAndClient;
use App\Services\Mikrotik\MikrotikClient;

class AdminVoucherController extends Controller
{
  use ResolvesRoleAndClient;

  public function index(Request $r)
  {
    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);
    $client = $this->resolveClientId($user, $r->query('client_id',''));
    $q = trim((string) $r->query('q',''));

    $rows = HotspotVoucher::query()
      ->when($client !== '', fn($qq) => $qq->where('client_id', $client))
      ->when($q !== '', function($qq) use ($q){
        $qq->where(function($w) use ($q){
          $w->where('name','like',"%$q%")
            ->orWhere('code','like',"%$q%")
            ->orWhere('profile','like',"%$q%");
        });
      })
      ->orderBy('client_id')->orderBy('price')
      ->paginate(20)->appends($r->only('client_id','q'));

    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) $clientsQuery->where('client_id', $client);
    $clients = $clientsQuery->get();

    return view('admin.vouchers.index', compact('rows','clients','client','q'));
  }

  public function create(Request $r, MikrotikClient $mt) // <— INJECT $mt
  {
    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);

    $voucher = new HotspotVoucher(['duration_minutes'=>60,'profile'=>'default','is_active'=>true]);

    $clientsQuery = Client::query()->orderBy('client_id');
    $clientId = strtoupper((string) $r->query('client_id',''));
    if (!$isAdmin) {
      $clientId = $this->requireUserClientId($user);
      $clientsQuery->where('client_id', $clientId);
    }
    $clients = $clientsQuery->get();

    // — Auto-load Mikrotik profiles/servers (best effort)
    $profiles = [];
    $servers  = [];
    $online   = false;

    if ($clientId !== '') {
      $selected = Client::where('client_id', $clientId)->first();
      if ($selected) {
        try {
          $m = $this->mtFor($mt, $selected);
          if (method_exists($m,'ping')) $m->ping(); else $m->raw('/system/identity/print');
          $online = true;

          try {
            $profiles = method_exists($m,'listHotspotProfiles')
              ? (array) $m->listHotspotProfiles()
              : collect($m->raw('/ip/hotspot/user/profile/print'))->pluck('name')->filter()->values()->all();

            $servers = method_exists($m,'listHotspotServers')
              ? (array) $m->listHotspotServers()
              : collect($m->raw('/ip/hotspot/print'))->pluck('name')->filter()->values()->all();
          } catch (\Throwable $e) { /* ignore */ }
        } catch (\Throwable $e) { /* offline */ }
      }
    }

    return view('admin.vouchers.form', compact('voucher','clients','profiles','servers','online','clientId'));
  }

  public function store(Request $r)
  {
    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);

    $data = $this->validated($r);
    if (!$isAdmin) $data['client_id'] = $this->requireUserClientId($user);
    $data['client_id'] = strtoupper((string) $data['client_id']);
    $data['price'] = $this->parseNominal($data['price']);

    HotspotVoucher::create($data);
    return redirect()->route('admin.vouchers.index')->with('ok','Voucher dibuat.');
  }

  public function edit(Request $r, HotspotVoucher $voucher, MikrotikClient $mt) // <— NEW
  {
    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);

    // list client yang boleh dipilih
    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) $clientsQuery->where('client_id', $this->requireUserClientId($user));
    $clients = $clientsQuery->get();

    // client yang aktif di dropdown (boleh override via ?client_id=)
    $clientId = strtoupper((string) ($r->query('client_id', $voucher->client_id ?: '')));

    // — Auto-load Mikrotik profiles/servers (best effort)
    $profiles = [];
    $servers  = [];
    $online   = false;

    if ($clientId !== '') {
      $selected = Client::where('client_id', $clientId)->first();
      if ($selected) {
        try {
          $m = $this->mtFor($mt, $selected);
          if (method_exists($m,'ping')) $m->ping(); else $m->raw('/system/identity/print');
          $online = true;

          try {
            $profiles = method_exists($m,'listHotspotProfiles')
              ? (array) $m->listHotspotProfiles()
              : collect($m->raw('/ip/hotspot/user/profile/print'))->pluck('name')->filter()->values()->all();

            $servers = method_exists($m,'listHotspotServers')
              ? (array) $m->listHotspotServers()
              : collect($m->raw('/ip/hotspot/print'))->pluck('name')->filter()->values()->all();
          } catch (\Throwable $e) { /* ignore */ }
        } catch (\Throwable $e) { /* offline */ }
      }
    }

    return view('admin.vouchers.form', compact('voucher','clients','profiles','servers','online','clientId'));
  }

  // — Helper koneksi Mikrotik (copas dari controller sebelumnya)
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

  public function update(Request $r, HotspotVoucher $voucher)
  {
    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);

    if (!$isAdmin && strtoupper((string)$voucher->client_id) !== $this->requireUserClientId($user)) {
      abort(403, 'Forbidden');
    }

    $data = $this->validated($r, $voucher->id);
    if (!$isAdmin) $data['client_id'] = $this->requireUserClientId($user);
    $data['client_id'] = strtoupper((string) $data['client_id']);
    $data['price'] = $this->parseNominal($data['price']);

    $voucher->update($data);
    return redirect()->route('admin.vouchers.index')->with('ok','Voucher diupdate.');
  }

  public function destroy(Request $r, HotspotVoucher $voucher)
  {
    $user = $r->user();
    if (!$this->userIsAdmin($user) && strtoupper((string)$voucher->client_id) !== $this->requireUserClientId($user)) {
      abort(403, 'Forbidden');
    }
    $voucher->delete();
    return back()->with('ok','Voucher dihapus.');
  }

  private function validated(Request $r, $ignoreId = null): array
  {
    return $r->validate([
      'client_id'        => ['required','string','max:12','regex:/^[A-Za-z0-9]+$/'],
      'name'             => ['required','string','max:120'],
      'code'             => ['nullable','string','max:120'],
      'price'            => ['required'],
      'duration_minutes' => ['required','integer','min:1','max:100000'],
      'profile'          => ['required','string','max:120'],
      'is_active'        => ['sometimes','boolean'],
    ]);
  }

  private function parseNominal($v): int
  {
    $n = (int) preg_replace('/\D+/', '', (string) $v);
    return max(0, $n);
  }
}
