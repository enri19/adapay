<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\HotspotVoucher;
use Illuminate\Http\Request;

class AdminVoucherController extends Controller
{
  public function index(Request $r)
  {
    $client = strtoupper((string) $r->query('client_id', ''));
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

    $clients = Client::orderBy('client_id')->get();

    return view('admin.vouchers.index', compact('rows','clients','client','q'));
  }

  public function create()
  {
    $voucher = new HotspotVoucher(['duration_minutes'=>60,'profile'=>'default','is_active'=>true]);
    $clients = \App\Models\Client::orderBy('client_id')->get();
    return view('admin.vouchers.form', compact('voucher','clients'));
  }

  public function store(Request $r)
  {
    $data = $this->validated($r);
    $data['price'] = $this->parseNominal($data['price']);

    HotspotVoucher::create($data);
    return redirect()->route('admin.vouchers.index')->with('ok','Voucher dibuat.');
  }

  public function edit(HotspotVoucher $voucher)
  {
    $clients = \App\Models\Client::orderBy('client_id')->get();
    return view('admin.vouchers.form', compact('voucher','clients'));
  }

  public function update(Request $r, HotspotVoucher $voucher)
  {
    $data = $this->validated($r, $voucher->id);
    $data['price'] = $this->parseNominal($data['price']);

    $voucher->update($data);
    return redirect()->route('admin.vouchers.index')->with('ok','Voucher diupdate.');
  }

  public function destroy(HotspotVoucher $voucher)
  {
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

  private function parseNominal($v): int
  {
    // terima "10.000", "10000", "Rp 10.000"
    $n = (int) preg_replace('/\D+/', '', (string) $v);
    return max(0, $n);
  }
}
