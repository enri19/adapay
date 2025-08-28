<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\HotspotVoucher;
use Illuminate\Http\Request;

class AdminVoucherController extends Controller
{
  public function index(Request $r)
  {
    $user = $r->user();
    $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : false;

    // Filter client: admin boleh pilih via query, user dipaksa ke miliknya
    $clientParam = trim((string) $r->query('client_id', ''));
    $client = $isAdmin ? strtoupper($clientParam) : $this->requireUserClientId($user);
    $q = trim((string) $r->query('q', ''));

    $rows = HotspotVoucher::query()
      ->when($client !== '', function($qq) use ($client){
        $qq->where('client_id', $client);
      })
      ->when($q !== '', function($qq) use ($q){
        $qq->where(function($w) use ($q){
          $w->where('name','like',"%$q%")
            ->orWhere('code','like',"%$q%")
            ->orWhere('profile','like',"%$q%");
        });
      })
      ->orderBy('client_id')
      ->orderBy('price')
      ->paginate(20)
      ->appends($r->only('client_id','q'));

    // Daftar client: admin = semua; user = hanya miliknya
    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) {
      $clientsQuery->where('client_id', $this->requireUserClientId($user));
    }
    $clients = $clientsQuery->get();

    return view('admin.vouchers.index', compact('rows','clients','client','q'));
  }

  public function create(Request $r)
  {
    $user = $r->user();
    $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : false;

    $voucher = new HotspotVoucher([
      'duration_minutes'=>60,
      'profile'=>'default',
      'is_active'=>true
    ]);

    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) {
      $clientsQuery->where('client_id', $this->requireUserClientId($user));
    }
    $clients = $clientsQuery->get();

    return view('admin.vouchers.form', compact('voucher','clients'));
  }

  public function store(Request $r)
  {
    $user = $r->user();
    $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : false;

    $data = $this->validated($r);
    // Non-admin: paksa client_id ke miliknya
    if (!$isAdmin) {
      $data['client_id'] = $this->requireUserClientId($user);
    }
    $data['client_id'] = strtoupper((string) $data['client_id']);
    $data['price'] = $this->parseNominal($data['price']);

    HotspotVoucher::create($data);
    return redirect()->route('admin.vouchers.index')->with('ok','Voucher dibuat.');
  }

  public function edit(Request $r, HotspotVoucher $voucher)
  {
    $user = $r->user();
    $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : false;

    // Non-admin hanya boleh mengedit milik client-nya
    if (!$isAdmin && strtoupper((string)$voucher->client_id) !== $this->requireUserClientId($user)) {
      abort(403, 'Forbidden');
    }

    $clientsQuery = \App\Models\Client::query()->orderBy('client_id');
    if (!$isAdmin) {
      $clientsQuery->where('client_id', $this->requireUserClientId($user));
    }
    $clients = $clientsQuery->get();

    return view('admin.vouchers.form', compact('voucher','clients'));
  }

  public function update(Request $r, HotspotVoucher $voucher)
  {
    $user = $r->user();
    $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : false;

    if (!$isAdmin && strtoupper((string)$voucher->client_id) !== $this->requireUserClientId($user)) {
      abort(403, 'Forbidden');
    }

    $data = $this->validated($r, $voucher->id);
    if (!$isAdmin) {
      // Non-admin tidak boleh pindah client; paksa tetap
      $data['client_id'] = $this->requireUserClientId($user);
    }
    $data['client_id'] = strtoupper((string) $data['client_id']);
    $data['price'] = $this->parseNominal($data['price']);

    $voucher->update($data);
    return redirect()->route('admin.vouchers.index')->with('ok','Voucher diupdate.');
  }

  public function destroy(Request $r, HotspotVoucher $voucher)
  {
    $user = $r->user();
    $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : false;

    if (!$isAdmin && strtoupper((string)$voucher->client_id) !== $this->requireUserClientId($user)) {
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
      'price'            => ['required'], // akan diparse ke integer
      'duration_minutes' => ['required','integer','min:1','max:100000'],
      'profile'          => ['required','string','max:120'],
      'is_active'        => ['sometimes','boolean'],
    ]);
  }

  private function userIsAdmin($user): bool
  {
    if (!$user) return false;
    if (method_exists($user, 'isAdmin')) return (bool) $user->isAdmin();
    if (isset($user->role)) return (string) $user->role === 'admin';
    return false;
  }

  private function requireUserClientId($user): string
  {
    // ADMIN: tidak perlu client filter → jangan abort, kembalikan '' agar query tidak di-filter client
    if ($this->userIsAdmin($user)) {
      return '';
    }

    // USER: wajib terikat client
    $clientId = strtoupper((string) ($user->client_id ?? ''));
    if ($clientId === '') {
      abort(403, 'User belum terikat ke client.');
    }
    return $clientId;
  }
}
