@extends('layouts.app')
@section('title', 'Beli Voucher Hotspot')

@push('head')
<style>
  /* ====== Box/Panel ====== */
  .panel{border:1px solid #e5e7eb;border-radius:1rem;background:#fff;padding:1rem}
  .panel--accent{background:linear-gradient(180deg,#f8fbff, #fff)}
  .panel-hd{display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem}
  .panel-ic{width:20px;height:20px;color:#0284c7}
  .subcard{border:1px solid #eef2f7;border-radius:.75rem;background:#fff}
  .subcard-hd{padding:.75rem .9rem;border-bottom:1px solid #eef2f7;font-weight:600}
  .subcard-bd{padding:.9rem}

  /* ====== Existing payment styles (ditambah dikit) ====== */
  .pay-section{margin-top:.5rem}
  .pay-header{display:flex;align-items:baseline;gap:.5rem;margin-bottom:.4rem}
  .pay-title{font-weight:700}
  .pay-desc{font-size:.85rem;color:#6b7280}
  .pay-methods{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem}
  .pm-card{
    display:flex;align-items:center;justify-content:center;gap:.5rem;
    border:1px solid #e5e7eb;border-radius:.6rem;background:#fff;padding:.6rem .75rem;
    cursor:pointer; user-select:none;
    transition:border-color .15s ease, box-shadow .15s ease, transform .06s ease, background-color .15s ease;
  }
  .pm-card:hover{ background:#fafafa }
  .pm-card:active{ transform:translateY(1px) }
  .pm-card[aria-checked="true"]{
    border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12);
  }
  .pm-radio{position:absolute;opacity:0;pointer-events:none;width:0;height:0}
  .pm-logo{width:84px;height:28px;object-fit:contain}
  @media (max-width:768px){ .pay-methods{grid-template-columns:1fr 1fr} }

  /* ====== Ringkasan ====== */
  .summary{border:1px solid #dbeafe;background:#f0f7ff;border-radius:.75rem;padding:.75rem}
  .summary-row{display:flex;justify-content:space-between;gap:.75rem;font-size:.92rem}
  .summary-row + .summary-row{margin-top:.25rem}
  .summary-total{font-weight:700}
</style>
@endpush

@section('content')
<div class="max-w-xl mx-auto p-1">
  <div class="panel panel--accent">
    <div class="panel-hd">
      <svg class="panel-ic" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm1 15h-2v-2h2Zm0-4h-2V7h2Z"/></svg>
      <div>
        <h1 class="text-xl font-semibold leading-tight">Beli Voucher Hotspot</h1>
        <p class="text-sm text-gray-600">Isi data, pilih voucher & metode, lalu lanjut bayar.</p>
      </div>
    </div>

    @php
      $isBaseHost = strtolower(request()->getHost()) === 'pay.adanih.info';
    @endphp

    <form id="frm" class="space-y-3" onsubmit="return startCheckout(event)" novalidate
      data-base-host="{{ $isBaseHost ? '1' : '0' }}">
      <input type="hidden" id="client_id" name="client_id" value="{{ $resolvedClientId ?? 'DEFAULT' }}">

      {{-- Pilih Client (muncul hanya di base host) --}}
      @if (!empty($isBaseHost) && $isBaseHost)
        <div class="subcard">
          <div class="subcard-hd">Pilih Client</div>
          <div class="subcard-bd">
            @if($clients->isEmpty())
              <div class="p-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded">
                Belum ada client aktif. Hubungi admin.
              </div>
            @else
              <label class="block text-sm font-medium mb-1">Client</label>
              <select id="clientSelect"
                      class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200"
                      onchange="location.href='{{ url('/hotspot') }}?client=' + encodeURIComponent(this.value)">
                @foreach($clients as $c)
                  <option value="{{ $c->client_id }}"
                    @if(isset($resolvedClientId) && $resolvedClientId === $c->client_id) selected @endif>
                    {{ $c->name }} ({{ $c->client_id }})
                  </option>
                @endforeach
              </select>
              <p class="text-xs text-gray-500 mt-1">Ganti client akan memuat ulang paket/voucher untuk client tersebut.</p>
            @endif
          </div>
        </div>
      @endif

      {{-- Subcard: Voucher --}}
      <div class="subcard">
        <div class="subcard-hd">Pilih Voucher</div>
        <div class="subcard-bd">
          @if($vouchers->isEmpty())
            <div class="p-3 text-sm text-gray-600 border rounded bg-gray-50">
              Belum ada voucher untuk lokasi ini.
            </div>
          @else
            <label class="block text-sm font-medium mb-1">Voucher</label>
            <select name="voucher_id" id="voucherSelect"
                    class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200" required>
              @foreach($vouchers as $v)
                <option value="{{ $v->id }}"
                        data-name="{{ $v->name }}"
                        data-price="{{ (int)$v->price }}">
                  {{ $v->name }} — Rp{{ number_format($v->price,0,',','.') }}
                </option>
              @endforeach
            </select>
          @endif
        </div>
      </div>

      {{-- Subcard: Identitas --}}
      <div class="subcard">
        <div class="subcard-hd">Data Pembeli</div>
        <div class="subcard-bd">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
            <div>
              <input name="name" id="fldName" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200"
                     placeholder="Nama lengkap" required minlength="2" pattern=".*\S.*">
              <p id="errName" class="text-xs text-red-600 mt-1 hidden"></p>
            </div>
            <div>
              <input name="phone" id="fldPhone" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200"
                     placeholder="No WhatsApp" required minlength="2" pattern=".*\S.*">
              <p id="errPhone" class="text-xs text-red-600 mt-1 hidden"></p>
            </div>
            <div>
              <input name="email" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200"
                     placeholder="Email (opsional)" type="email">
            </div>
          </div>
          <div class="mt-2 text-xs text-gray-500">
            Transaksi diproses aman. Kredensial voucher tampil otomatis setelah pembayaran terkonfirmasi.
          </div>
        </div>
      </div>

      {{-- Subcard: Metode Pembayaran --}}
      <div class="subcard">
        <div class="subcard-hd">Metode Pembayaran</div>
        <div class="subcard-bd">
          {{-- QRIS (disarankan) --}}
          <div class="pay-section">
            <div class="pay-header">
              <div class="pay-title">QRIS <span class="text-xs font-normal text-emerald-700 px-2 py-0.5 border rounded-full">Disarankan</span></div>
              <div class="pay-desc">Pembayaran DANA & e-wallet lain via scan QR dari m-banking/e-wallet.</div>
            </div>
            <div class="pay-methods" role="radiogroup" aria-label="QRIS">
              <label class="pm-card" data-value="qris" tabindex="0" role="radio" aria-checked="true">
                <input class="pm-radio" type="radio" name="method" value="qris" checked>
                <img class="pm-logo" src="{{ asset('images/pay/qris.svg') }}" alt="QRIS"
                     onerror="this.replaceWith(document.createTextNode('QRIS'))">
              </label>
            </div>
          </div>

          {{-- E-Wallet langsung --}}
          <div class="pay-section">
            <div class="pay-header">
              <div class="pay-title">E-Wallet langsung</div>
              <div class="pay-desc">Bayar langsung di aplikasi (tanpa scan QR).</div>
            </div>
            <div class="pay-methods" role="radiogroup" aria-label="E-Wallet">
              <label class="pm-card" data-value="gopay" tabindex="0" role="radio" aria-checked="false">
                <input class="pm-radio" type="radio" name="method" value="gopay">
                <img class="pm-logo" src="{{ asset('images/pay/gopay.svg') }}" alt="GoPay"
                     onerror="this.replaceWith(document.createTextNode('GoPay'))">
              </label>
              <label class="pm-card" data-value="shopeepay" tabindex="0" role="radio" aria-checked="false">
                <input class="pm-radio" type="radio" name="method" value="shopeepay">
                <img class="pm-logo" src="{{ asset('images/pay/shopeepay.svg') }}" alt="ShopeePay"
                     onerror="this.replaceWith(document.createTextNode('ShopeePay'))">
              </label>
            </div>
          </div>
        </div>
      </div>

      {{-- Ringkasan Pesanan --}}
      <div class="summary">
        <div class="summary-row">
          <span>Voucher</span>
          <span id="sumVoucherName">—</span>
        </div>
        <div class="summary-row">
          <span>Metode</span>
          <span id="sumMethod">QRIS</span>
        </div>
        <hr class="my-2 border-blue-100">
        <div class="summary-row summary-total">
          <span>Total</span>
          <span id="sumTotal">Rp0</span>
        </div>
      </div>

      <div id="payErr" class="text-xs text-red-600 hidden"></div>

      <button id="payBtn" type="submit" class="btn btn--primary" {{ $vouchers->isEmpty() ? 'disabled' : '' }}>
        <span class="btn__label">Bayar</span>
        <span class="spinner hidden" aria-hidden="true"></span>
      </button>

      <div class="text-xs text-gray-500">
        Dengan melanjutkan, kamu menyetujui
        <a href="{{ url('/agreement') }}" class="underline text-sky-700">Perjanjian Layanan</a>
        & <a href="{{ url('/privacy') }}" class="underline text-sky-700">Kebijakan Privasi</a>.
      </div>

      @if($vouchers->isEmpty())
        <p class="text-xs text-gray-500">Tombol bayar non-aktif karena belum ada voucher.</p>
      @endif
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  // ========= Utils umum =========
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
  function sanitizeClientId(s){
    return (String(s||'').toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,12) || 'DEFAULT');
  }
  function isBaseHost(){ return location.hostname.toLowerCase() === 'pay.adanih.info'; }
  function rupiah(n){
    const x = Math.max(0, parseInt(n||0,10));
    return 'Rp' + x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  // ========= Elemen DOM =========
  const formEl     = document.getElementById('frm');
  const payBtn     = document.getElementById('payBtn');
  const errBox     = document.getElementById('payErr');
  const hidClient  = document.getElementById('client_id');
  const selClient  = document.getElementById('clientSelect');  // hanya ada di base host
  const selVoucher = document.getElementById('voucherSelect');
  const nameInput  = document.getElementById('fldName');
  const phoneInput = document.getElementById('fldPhone');

  const sumName    = document.getElementById('sumVoucherName');
  const sumTotal   = document.getElementById('sumTotal');
  const sumMethod  = document.getElementById('sumMethod');
  const noVoucher  = document.getElementById('noVoucherBox');

  // pakai URL absolut biar aman walau route name belum diset
  const VOUCHERS_URL = "{{ url('/api/hotspot/vouchers') }}";

  // ========= Client resolver =========
  function getResolvedClientId(){
    // *.pay.adanih.info -> ambil subdomain
    if (!isBaseHost()){
      const parts = location.hostname.split('.');
      if (parts.length > 3 && parts[0]) return sanitizeClientId(parts[0]);
    }
    // base host -> ambil dari select (kalau ada) atau dari query ?client
    if (selClient && selClient.value) return sanitizeClientId(selClient.value);
    const q = new URLSearchParams(location.search).get('client');
    if (q) return sanitizeClientId(q);
    return sanitizeClientId(hidClient ? hidClient.value : 'DEFAULT');
  }

  // ========= Validasi field =========
  function errElFor(input){ const id=(input?.id||'').replace(/^fld/,'err'); return document.getElementById(id); }
  function showFieldError(input,msg){
    const err=errElFor(input); if(!err||!input) return;
    if(msg){ err.textContent=msg; err.classList.remove('hidden'); input.classList.add('border-red-500','focus:ring-red-200'); input.setAttribute('aria-invalid','true'); }
    else { err.textContent=''; err.classList.add('hidden'); input.classList.remove('border-red-500','focus:ring-red-200'); input.removeAttribute('aria-invalid'); }
  }
  function validateName(input){
    const v=String(input?.value||'').trim();
    if (v.length<2) return 'Nama wajib diisi (min. 2 karakter).';
    if (!/\S/.test(v)) return 'Nama tidak boleh hanya spasi.';
    return '';
  }
  function normalizeIdPhone(raw){
    const s=String(raw||'').trim(); if(!s) return {norm:'',ok:false};
    const plus62=s.startsWith('+62'); const digits=s.replace(/\D+/g,'');
    let norm=digits;
    if (plus62) norm='62'+digits.slice(2);
    else if (digits.startsWith('62')) norm=digits;
    else if (digits.startsWith('0')) norm='62'+digits.slice(1);
    const ok=/^62[0-9]{9,13}$/.test(norm) && /^62[8]/.test(norm);
    return {norm,ok};
  }
  function validatePhone(input){
    const v=String(input?.value||'').trim();
    if(!v) return {msg:'No WhatsApp wajib diisi.',norm:''};
    const {norm,ok}=normalizeIdPhone(v);
    if(!ok) return {msg:'Format WA harus diawali 08 / 62 / +62 dan valid.',norm:''};
    return {msg:'',norm};
  }

  // ========= Ringkasan =========
  function currentMethod(){
    const r=document.querySelector('.pm-radio:checked');
    return (r && (r.value||'').toUpperCase()) || 'QRIS';
  }
  function updateSummary(){
    const opt = selVoucher ? selVoucher.options[selVoucher.selectedIndex] : null;
    const name = opt ? (opt.getAttribute('data-name') || opt.textContent || '—') : '—';
    const price = opt ? parseInt(opt.getAttribute('data-price') || 0,10) : 0;
    if (sumName)   sumName.textContent  = name;
    if (sumTotal)  sumTotal.textContent = rupiah(price);
    if (sumMethod) sumMethod.textContent = currentMethod();
  }

  // ========= Voucher AJAX (tanpa jQuery) =========
  function rebuildVouchers(list){
    if (!selVoucher) return;
    selVoucher.innerHTML = '';
    if (!list || !list.length){
      if (noVoucher) noVoucher.classList.remove('hidden');
      selVoucher.disabled = true;
      if (payBtn) payBtn.disabled = true;
      updateSummary();
      return;
    }
    if (noVoucher) noVoucher.classList.add('hidden');
    list.forEach(v=>{
      const o=document.createElement('option');
      o.value=v.id;
      o.textContent=`${v.name} — ${rupiah(v.price)}`;
      o.setAttribute('data-name', v.name);
      o.setAttribute('data-price', v.price);
      selVoucher.appendChild(o);
    });
    selVoucher.disabled = false;
    if (payBtn) payBtn.disabled = false;
    updateSummary();
  }

  async function fetchVouchers(cid){
    try{
      if (hidClient) hidClient.value = cid;
      if (selVoucher){
        selVoucher.disabled = true;
        selVoucher.innerHTML = '<option>Memuat…</option>';
      }
      if (payBtn) payBtn.disabled = true;

      const url = VOUCHERS_URL + '?client=' + encodeURIComponent(cid);
      const res = await fetch(url, { headers: { 'Accept':'application/json' } });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const json = await res.json();
      rebuildVouchers((json && json.data) ? json.data : []);
    }catch(e){
      console.error('Gagal memuat voucher:', e);
      rebuildVouchers([]);
    }
  }

  // ========= Event handlers =========
  // set hidden client id saat load
  document.addEventListener('DOMContentLoaded', function(){
    const cid = getResolvedClientId();
    if (hidClient) hidClient.value = cid;

    if (nameInput){
      nameInput.addEventListener('input', ()=>showFieldError(nameInput, validateName(nameInput)));
      nameInput.addEventListener('blur',  ()=>showFieldError(nameInput, validateName(nameInput)));
    }
    if (phoneInput){
      phoneInput.addEventListener('input', ()=>showFieldError(phoneInput, validatePhone(phoneInput).msg));
      phoneInput.addEventListener('blur',  ()=>showFieldError(phoneInput, validatePhone(phoneInput).msg));
    }
    if (selVoucher){ selVoucher.addEventListener('change', updateSummary); }
    if (selClient){  // base host: ganti client tanpa reload
      selClient.addEventListener('change', ()=>{
        const newCid = sanitizeClientId(selClient.value || 'DEFAULT');
        fetchVouchers(newCid);
      });
    }
    // init summary
    updateSummary();
  });

  // metode pembayaran (klik kartu)
  document.addEventListener('DOMContentLoaded', function(){
    function setChecked(card){
      document.querySelectorAll('.pm-card').forEach(c=>{
        c.setAttribute('aria-checked','false');
        const r=c.querySelector('.pm-radio'); if(r) r.checked=false;
      });
      card.setAttribute('aria-checked','true');
      const radio=card.querySelector('.pm-radio'); if(radio) radio.checked=true;
      updateSummary();
    }
    const current=document.querySelector('.pm-radio:checked');
    if (current){ const card=current.closest('.pm-card'); if(card) setChecked(card); }
    document.querySelectorAll('.pay-methods').forEach(group=>{
      group.addEventListener('click', e=>{
        const card=e.target.closest('.pm-card'); if(!card) return; setChecked(card);
      });
      group.addEventListener('keydown', e=>{
        if (e.key===' ' || e.key==='Enter'){
          const card=e.target.closest('.pm-card'); if(!card) return;
          e.preventDefault(); setChecked(card);
        }
      });
    });
  });

  // ========= Submit checkout =========
  window.startCheckout = async function(e){
    e.preventDefault();
    if (errBox){ errBox.classList.add('hidden'); errBox.textContent=''; }

    // Validasi
    const nameMsg = validateName(nameInput);
    if (nameMsg){ showFieldError(nameInput, nameMsg); return false; } else { showFieldError(nameInput, null); }
    const { msg: phoneMsg, norm: phoneNorm } = validatePhone(phoneInput);
    if (phoneMsg){ showFieldError(phoneInput, phoneMsg); return false; } else { showFieldError(phoneInput, null); }

    // Payload
    const cid = getResolvedClientId();
    if (hidClient) hidClient.value = cid;

    const voucherId = selVoucher ? Number(selVoucher.value) : null;
    const method = (document.querySelector('.pm-radio:checked')?.value || 'qris').toLowerCase();
    const payload = {
      voucher_id: voucherId,
      method: method,
      name: String(nameInput.value||'').trim(),
      email: (formEl && formEl.querySelector('[name="email"]')) ? formEl.querySelector('[name="email"]').value || null : null,
      phone: phoneNorm || String(phoneInput.value||'').trim(),
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
      let data; try{ data = JSON.parse(text); } catch{ throw new Error('RESP_INVALID: ' + text.slice(0,120)); }

      if(!res.ok){
        if (res.status === 422) {
          const errs = (data && data.errors) || {};
          if (errs.name && errs.name[0])  showFieldError(nameInput,  errs.name[0]);
          if (errs.phone && errs.phone[0]) showFieldError(phoneInput, errs.phone[0]);
          const msg = data.message || errs.name?.[0] || errs.phone?.[0] || 'Data tidak valid.';
          throw new Error(msg);
        }
        const code = data.error || 'CHECKOUT_FAILED';
        let msg = data.message || 'Gagal membuat transaksi.';
        if(code==='UPSTREAM_TEMPORARY') msg='Channel pembayaran sedang gangguan. Coba lagi.';
        if(code==='CHANNEL_INACTIVE')  msg='Channel belum aktif di dashboard Midtrans.';
        if(code==='POP_REQUIRED')      msg='Akun butuh PoP/aktivasi tambahan di Midtrans.';
        throw new Error(msg);
      }

      const orderId = data.order_id || (data.midtrans && data.midtrans.order_id);
      if(!orderId) throw new Error('Order ID tidak ditemukan.');

      setLoading(payBtn, true, 'Mengarahkan…');
      window.location.href = '/hotspot/order/' + encodeURIComponent(orderId);
      return false;

    }catch(e){
      setLoading(payBtn, false);
      if (errBox){ errBox.textContent = e.message || 'Terjadi kesalahan.'; errBox.classList.remove('hidden'); }
      return false;
    }
  };
})();
</script>
@endpush
