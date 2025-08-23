@extends('layouts.app')
@section('title', 'Beli Voucher Hotspot')

@section('content')
<div class="max-w-xl mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-4">Beli Voucher Hotspot</h1>

  <form id="frm" class="space-y-3" onsubmit="return startCheckout(event)">
    <input type="hidden" id="client_id" name="client_id" value="{{ $resolvedClientId ?? 'DEFAULT' }}">

    <label class="block">
      <span class="text-sm">Pilih voucher</span>
      <select name="voucher_id" class="border rounded p-2 w-full">
        @foreach($vouchers as $v)
          <option value="{{ $v->id }}">{{ $v->name }} — Rp{{ number_format($v->price,0,',','.') }}</option>
        @endforeach
      </select>
    </label>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
      <input name="name" class="border rounded p-2" placeholder="Nama (opsional)">
      <input name="email" class="border rounded p-2" placeholder="Email (opsional)">
      <input name="phone" class="border rounded p-2" placeholder="HP (opsional)">
    </div>

    <div class="flex items-center gap-3">
      <label class="flex items-center gap-1"><input type="radio" name="method" value="qris" checked> <span>QRIS</span></label>
      <label class="flex items-center gap-1"><input type="radio" name="method" value="gopay"> <span>GoPay</span></label>
      <label class="flex items-center gap-1"><input type="radio" name="method" value="shopeepay"> <span>ShopeePay</span></label>
    </div>

    <div id="payErr" class="text-xs text-red-600 hidden"></div>

    <button id="payBtn" type="submit" class="btn btn--primary">
      <span class="btn__label">Bayar</span>
      <span class="spinner hidden" aria-hidden="true"></span>
    </button>
  </form>
</div>
@endsection

@push('scripts')
<script>
(function(){
  console.log("{{ $resolvedClientId ?? 'DEFAULT' }}");
  function setLoading(btn,on,txt){
    if(!btn) return;
    const label = btn.querySelector('.btn__label');
    const spin  = btn.querySelector('.spinner');
    btn.toggleAttribute('disabled', !!on);
    btn.setAttribute('aria-busy', on ? 'true' : 'false');
    if(spin) spin.classList.toggle('hidden', !on);
    if(label){
      if(btn.__orig==null) btn.__orig = label.textContent;
      label.textContent = (on && txt) ? txt : btn.__orig;
    }
  }

  function getClientIdFromHost(){
    // subdomain: c1.pay.adanih.info -> C1
    var h = location.hostname.split('.');
    if (h.length > 2) {
      return (h[0] || 'DEFAULT').toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,12) || 'DEFAULT';
    }
    // fallback: ?client=C1
    var q = new URLSearchParams(location.search).get('client') || 'DEFAULT';
    return q.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,12);
  }

  const payBtn = document.getElementById('payBtn');
  const errBox = document.getElementById('payErr');

  window.startCheckout = async function(e){
    e.preventDefault();
    errBox.classList.add('hidden'); errBox.textContent = '';

    // ambil data form dengan tipe yang tepat
    const form = new FormData(document.getElementById('frm'));
    const payload = {
      voucher_id: Number(form.get('voucher_id')),
      method: (form.get('method') || 'qris').toLowerCase(),
      name: form.get('name') || null,
      email: form.get('email') || null,
      phone: form.get('phone') || null,
      client_id: form.get('client_id') || null,
    };

    setLoading(payBtn, true, 'Memproses…');
    try{
      const res = await fetch('/api/hotspot/checkout', {
        method:'POST',
        headers:{'Content-Type':'application/json','Accept':'application/json'},
        body: JSON.stringify(payload)
      });

      // parse aman (antisipasi HTML error)
      const text = await res.text();
      let data; try{ data = JSON.parse(text); }catch{
        throw new Error('RESP_INVALID: ' + text.slice(0,120));
      }

      if(!res.ok){
        const code = data.error || 'CHECKOUT_FAILED';
        let msg = data.message || 'Gagal membuat transaksi.';
        if(code==='UPSTREAM_TEMPORARY') msg = 'Channel pembayaran sedang gangguan (sandbox). Coba lagi.';
        if(code==='CHANNEL_INACTIVE')  msg = 'Channel belum aktif di dashboard Midtrans.';
        if(code==='POP_REQUIRED')       msg = 'Akun butuh PoP/aktivasi tambahan di Midtrans.';
        throw new Error(msg);
      }

      const orderId = data.order_id || (data.midtrans && data.midtrans.order_id);
      if(!orderId) throw new Error('Order ID tidak ditemukan.');

      setLoading(payBtn, true, 'Mengarahkan…');
      window.location.href = '/hotspot/order/' + encodeURIComponent(orderId);
      return false;

    }catch(e){
      setLoading(payBtn, false);
      errBox.textContent = e.message || 'Terjadi kesalahan.';
      errBox.classList.remove('hidden');
      return false;
    }
  }
})();
</script>
@endpush
