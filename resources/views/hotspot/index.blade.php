@extends('layouts.app')
@section('title', 'Beli Voucher Hotspot')

@push('head')
<style>
  .pay-section{margin-top:.5rem}
  .pay-header{display:flex;align-items:baseline;gap:.5rem;margin-bottom:.4rem}
  .pay-title{font-weight:700}
  .pay-desc{font-size:.85rem;color:#6b7280}
  .pay-methods{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem}
  .pm-card{
    display:flex;align-items:center;justify-content:center;gap:.5rem;
    border:1px solid #e5e7eb;border-radius:.6rem;background:#fff;padding:.6rem .75rem;
    cursor:pointer; user-select:none;
    transition:border-color .15s ease, box-shadow .15s ease, transform .06s ease;
  }
  .pm-card:hover{ background:#fafafa }
  .pm-card:active{ transform:translateY(1px) }
  .pm-card[aria-checked="true"]{
    border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15);
  }
  .pm-radio{position:absolute;opacity:0;pointer-events:none;width:0;height:0}
  .pm-logo{width:84px;height:28px;object-fit:contain}
  @media (max-width:768px){ .pay-methods{grid-template-columns:1fr 1fr} }
</style>
@endpush

@section('content')
<div class="max-w-xl mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-1">Beli Voucher Hotspot</h1>
  <p class="text-sm text-gray-600 mb-4">Isi nama, pilih voucher & metode, lalu lanjut bayar.</p>

  <form id="frm" class="space-y-3" onsubmit="return startCheckout(event)" novalidate>
    <input type="hidden" id="client_id" name="client_id" value="{{ $resolvedClientId ?? 'DEFAULT' }}">

    {{-- Voucher --}}
    <label class="block">
      <span class="text-sm font-medium">Pilih voucher</span>
      @if($vouchers->isEmpty())
        <div class="p-3 text-sm text-gray-600 border rounded bg-gray-50">
          Belum ada voucher untuk lokasi ini.
        </div>
      @else
        <select name="voucher_id" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200" required>
          @foreach($vouchers as $v)
            <option value="{{ $v->id }}">{{ $v->name }} — Rp{{ number_format($v->price,0,',','.') }}</option>
          @endforeach
        </select>
      @endif
    </label>

    {{-- Identitas --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
      <div>
        <input name="name" id="fldName" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200" placeholder="Nama lengkap" required minlength="2" pattern=".*\S.*">
        <p id="errName" class="text-xs text-red-600 mt-1 hidden"></p>
      </div>
      <div>
        <input name="phone" id="fldPhone" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200" placeholder="No Whatsapp" required minlength="2" pattern=".*\S.*">
        <p id="errPhone" class="text-xs text-red-600 mt-1 hidden"></p>
      </div>
      <input name="email" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200" placeholder="Email (opsional)" type="email">
    </div>

    {{-- Metode: QRIS (disarankan) --}}
    <div class="pay-section">
      <div class="pay-header">
        <div class="pay-title">QRIS (disarankan)</div>
        <div class="pay-desc">Pembayaran DANA dan e-wallet lain (mis. OVO, LinkAja, m-banking yang mendukung QRIS) melalui scan QR.</div>
      </div>
      <div class="pay-methods" role="radiogroup" aria-label="QRIS">
        <label class="pm-card" data-value="qris" tabindex="0" role="radio" aria-checked="true">
          <input class="pm-radio" type="radio" name="method" value="qris" checked>
          <img class="pm-logo"
              src="{{ asset('images/pay/qris.svg') }}"
              alt="QRIS"
              onerror="this.replaceWith(document.createTextNode('QRIS'))">
        </label>
      </div>
    </div>

    {{-- Metode: E-Wallet langsung --}}
    <div class="pay-section">
      <div class="pay-header">
        <div class="pay-title">E-Wallet langsung</div>
        <div class="pay-desc">Bayar langsung di aplikasi (tanpa scan QR).</div>
      </div>
      <div class="pay-methods" role="radiogroup" aria-label="E-Wallet">
        {{-- GoPay --}}
        <label class="pm-card" data-value="gopay" tabindex="0" role="radio" aria-checked="false">
          <input class="pm-radio" type="radio" name="method" value="gopay">
          <img class="pm-logo"
              src="{{ asset('images/pay/gopay.svg') }}"
              alt="GoPay"
              onerror="this.replaceWith(document.createTextNode('GoPay'))">
        </label>

        {{-- ShopeePay --}}
        <label class="pm-card" data-value="shopeepay" tabindex="0" role="radio" aria-checked="false">
          <input class="pm-radio" type="radio" name="method" value="shopeepay">
          <img class="pm-logo"
              src="{{ asset('images/pay/shopeepay.svg') }}"
              alt="ShopeePay"
              onerror="this.replaceWith(document.createTextNode('ShopeePay'))">
        </label>
      </div>
    </div>

    <div id="payErr" class="text-xs text-red-600 hidden"></div>

    <button id="payBtn" type="submit" class="btn btn--primary" {{ $vouchers->isEmpty() ? 'disabled' : '' }}>
      <span class="btn__label">Bayar</span>
      <span class="spinner hidden" aria-hidden="true"></span>
    </button>

    @if($vouchers->isEmpty())
      <p class="text-xs text-gray-500">Tombol bayar non-aktif karena belum ada voucher.</p>
    @endif
  </form>
</div>
@endsection

@push('scripts')
<script>
(function(){
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
    const parts = location.hostname.split('.');
    if (parts.length > 2) {
      return (parts[0] || 'DEFAULT').toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,12) || 'DEFAULT';
    }
    const q = new URLSearchParams(location.search).get('client') || 'DEFAULT';
    return q.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,12);
  }

  function errElFor(input){
    // fldName -> errName, fldPhone -> errPhone
    const errId = (input?.id || '').replace(/^fld/, 'err');
    return document.getElementById(errId);
  }

  function showFieldError(input, msg){
    const err = errElFor(input);
    if (!err || !input) return;
    if (msg){
      err.textContent = msg;
      err.classList.remove('hidden');
      input.classList.add('border-red-500','focus:ring-red-200');
      input.setAttribute('aria-invalid','true');
      input.focus();
    } else {
      err.textContent = '';
      err.classList.add('hidden');
      input.classList.remove('border-red-500','focus:ring-red-200');
      input.removeAttribute('aria-invalid');
    }
  }

  // === Validators ===
  function validateName(input){
    const v = String(input?.value || '').trim();
    if (v.length < 2) return 'Nama wajib diisi (min. 2 karakter).';
    if (!/\S/.test(v)) return 'Nama tidak boleh hanya spasi.';
    return '';
  }

  function normalizeIdPhone(raw){
    const s = String(raw || '').trim();
    if (!s) return { norm: '', digits: '', ok: false };

    const plus62 = s.startsWith('+62');
    const digits = s.replace(/\D+/g,''); // buang non-digit

    let norm = digits;
    if (plus62) {
      norm = '62' + digits.slice(2);
    } else if (digits.startsWith('62')) {
      norm = digits;
    } else if (digits.startsWith('0')) {
      norm = '62' + digits.slice(1);
    }

    // aturan longgar: harus 62 + 8xxxxxxxx, panjang 10–15 digit total
    const ok = /^62[0-9]{9,13}$/.test(norm) && /^62[8]/.test(norm);
    return { norm, digits, ok };
  }

  function validatePhone(input){
    const v = String(input?.value || '').trim();
    if (!v) return { msg: 'No WhatsApp wajib diisi.', norm: '' };

    const { norm, ok } = normalizeIdPhone(v);
    if (!ok) {
      return { msg: 'Format WA harus diawali 08 / 62 / +62 dan valid.', norm: '' };
    }
    return { msg: '', norm };
  }

  // set hidden client_id saat load + live validation
  document.addEventListener('DOMContentLoaded', function(){
    const hid = document.getElementById('client_id');
    const cid = getClientIdFromHost();
    if (hid) hid.value = cid;

    const nameInput  = document.getElementById('fldName');
    const phoneInput = document.getElementById('fldPhone');

    if (nameInput){
      nameInput.addEventListener('input', function(){
        const msg = validateName(nameInput);
        showFieldError(nameInput, msg);
      });
      nameInput.addEventListener('blur', function(){
        const msg = validateName(nameInput);
        showFieldError(nameInput, msg);
      });
    }

    if (phoneInput){
      phoneInput.addEventListener('input', function(){
        const { msg } = validatePhone(phoneInput);
        showFieldError(phoneInput, msg);
      });
      phoneInput.addEventListener('blur', function(){
        const { msg } = validatePhone(phoneInput);
        showFieldError(phoneInput, msg);
      });
    }
  });

  const payBtn = document.getElementById('payBtn');
  const errBox = document.getElementById('payErr');

  window.startCheckout = async function(e){
    e.preventDefault();
    errBox.classList.add('hidden'); errBox.textContent = '';

    const formEl     = document.getElementById('frm');
    const nameInput  = document.getElementById('fldName');
    const phoneInput = document.getElementById('fldPhone');

    // Validasi nama
    const nameMsg = validateName(nameInput);
    if (nameMsg){
      showFieldError(nameInput, nameMsg);
      return false;
    } else {
      showFieldError(nameInput, null);
    }

    // Validasi phone
    const { msg: phoneMsg, norm: phoneNorm } = validatePhone(phoneInput);
    if (phoneMsg){
      showFieldError(phoneInput, phoneMsg);
      return false;
    } else {
      showFieldError(phoneInput, null);
    }

    // kumpulkan data
    const form = new FormData(formEl);
    const cid = getClientIdFromHost();
    form.set('client_id', cid);
    const hid = document.getElementById('client_id'); if (hid) hid.value = cid;

    const payload = {
      voucher_id: Number(form.get('voucher_id')),
      method: (form.get('method') || 'qris').toLowerCase(),
      name: String(nameInput.value || '').trim(),
      email: form.get('email') || null,
      phone: phoneNorm || String(phoneInput.value || '').trim(),
      client_id: cid,
    };

    setLoading(payBtn, true, 'Memproses…');
    try{
      const res = await fetch('/api/hotspot/checkout', {
        method:'POST',
        headers:{'Content-Type':'application/json','Accept':'application/json'},
        body: JSON.stringify(payload)
      });

      const text = await res.text();
      let data; try{ data = JSON.parse(text); }catch{
        throw new Error('RESP_INVALID: ' + text.slice(0,120));
      }

      if(!res.ok){
        if (res.status === 422) {
          // tampilkan error spesifik field bila ada
          const errs = (data && data.errors) || {};
          if (errs.name && errs.name[0])  showFieldError(nameInput, errs.name[0]);
          if (errs.phone && errs.phone[0]) showFieldError(phoneInput, errs.phone[0]);

          const msg = data.message || errs.name?.[0] || errs.phone?.[0] || 'Data tidak valid.';
          throw new Error(msg);
        }
        const code = data.error || 'CHECKOUT_FAILED';
        let msg = data.message || 'Gagal membuat transaksi.';
        if(code==='UPSTREAM_TEMPORARY') msg = 'Channel pembayaran sedang gangguan (sandbox). Coba lagi.';
        if(code==='CHANNEL_INACTIVE')  msg = 'Channel belum aktif di dashboard Midtrans.';
        if(code==='POP_REQUIRED')      msg = 'Akun butuh PoP/aktivasi tambahan di Midtrans.';
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

  // Metode pembayaran
  document.addEventListener('DOMContentLoaded', function(){
    const groups = Array.from(document.querySelectorAll('.pay-methods'));

    function setChecked(card){
      document.querySelectorAll('.pm-card').forEach(c=>{
        c.setAttribute('aria-checked','false');
        const r = c.querySelector('.pm-radio'); if (r) r.checked = false;
      });
      card.setAttribute('aria-checked','true');
      const radio = card.querySelector('.pm-radio'); if (radio) radio.checked = true;
    }

    const current = document.querySelector('.pm-radio:checked');
    if (current) {
      const card = current.closest('.pm-card');
      if (card) setChecked(card);
    }

    groups.forEach(group=>{
      group.addEventListener('click', function(e){
        const card = e.target.closest('.pm-card'); if(!card) return;
        setChecked(card);
      });
      group.addEventListener('keydown', function(e){
        if (e.key === ' ' || e.key === 'Enter'){
          const card = e.target.closest('.pm-card'); if(!card) return;
          e.preventDefault(); setChecked(card);
        }
      });
    });
  });
})();
</script>
@endpush
