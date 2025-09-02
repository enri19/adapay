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

  select:disabled{ background:#f9fafb; color:#6b7280; }
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

    <form id="frm" class="space-y-3" onsubmit="return startCheckout(event)" novalidate data-base-host="{{ $isBaseHost ? '1' : '0' }}">
      {{-- Picker Client (tampil hanya di base host) --}}
      @if(!empty($isBaseHost) && $isBaseHost)
      <div class="subcard">
        <div class="subcard-hd">Pilih Mitra</div>
        <div class="subcard-bd">
          <label class="block text-sm font-medium mb-1">Mitra</label>
          <select id="clientSelect" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200" autocomplete="off">
            <option value="" disabled @if(empty($resolvedClientId)) selected @endif>— Pilih Client —</option>
            @foreach($clients as $c)
              <option value="{{ $c->client_id }}"
                      data-slug="{{ $c->slug }}"
                      @if(!empty($resolvedClientId) && $resolvedClientId === $c->client_id) selected @endif>
                {{ $c->name }} ({{ $c->client_id }})
              </option>
            @endforeach
          </select>
        </div>
      </div>
      @endif

      {{-- Hidden client_id: satu saja, kosong jika belum pilih --}}
      <input type="hidden" id="client_id" name="client_id" value="{{ $resolvedClientId ?? '' }}">

      {{-- Subcard: Voucher (SELALU render select) --}}
      <div class="subcard">
        <div class="subcard-hd">Pilih Voucher</div>
        <div class="subcard-bd">
          <label class="block text-sm font-medium mb-1">Voucher</label>

          <select id="voucherSelect" name="voucher_id"
                  class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200"
                  @if(empty($resolvedClientId)) disabled @endif required>
            @if(!empty($resolvedClientId))
              @foreach($vouchers as $v)
                <option value="{{ $v->id }}" data-name="{{ $v->name }}" data-price="{{ (int)$v->price }}">
                  {{ $v->name }} — Rp{{ number_format($v->price,0,',','.') }}
                </option>
              @endforeach
            @endif
          </select>

          <div id="noVoucherBox"
              class="mt-2 p-3 text-sm text-gray-600 border rounded bg-gray-50 @if(!empty($resolvedClientId) && $vouchers->count()) hidden @endif">
            @if(empty($resolvedClientId))
              Pilih client untuk menampilkan voucher.
            @else
              Belum ada voucher untuk client ini.
            @endif
          </div>
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

      <button id="payBtn" type="submit" class="btn btn--primary"
        @if(empty($resolvedClientId) || $vouchers->isEmpty()) disabled @endif>
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
  // ===== Helpers =====
  const $  = (s,sc)=> (sc||document).querySelector(s);
  const $$ = (s,sc)=> Array.from((sc||document).querySelectorAll(s));
  const isBaseHost = ()=> location.hostname.toLowerCase() === 'pay.adanih.info';
  const sanitizeClientId = s => String(s||'').toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,12);
  const rupiah = n => 'Rp' + Math.max(0,parseInt(n||0,10)).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.');

  function setLoading(btn,on,txt){
    if(!btn) return;
    const label=btn.querySelector('.btn__label'), spin=btn.querySelector('.spinner');
    btn.toggleAttribute('disabled', !!on);
    btn.setAttribute('aria-busy', on?'true':'false');
    if(spin) spin.classList.toggle('hidden', !on);
    if(label){ if(btn.__orig==null) btn.__orig=label.textContent; label.textContent=(on&&txt)?txt:btn.__orig; }
  }

  // ===== Elements (beberapa pakai getter agar selalu up-to-date) =====
  const formEl   = $('#frm');
  const selClient= $('#clientSelect');
  const hidClient= $('#client_id');
  const payBtn   = $('#payBtn');
  const errBox   = $('#payErr');
  const noVoucher= $('#noVoucherBox');
  const nameInput= $('#fldName');
  const phoneInput=$('#fldPhone');
  const emailInput=formEl ? formEl.querySelector('[name="email"]') : null;

  const sumName  = $('#sumVoucherName');
  const sumTotal = $('#sumTotal');
  const sumMethod= $('#sumMethod');

  const API_VOUCHERS_URL = "{{ url('/api/hotspot/vouchers') }}";
  const API_CHECKOUT_URL = "{{ url('/api/hotspot/checkout') }}";

  // Getter supaya aman walau markup awal kosong
  function getVoucherSel(){ return document.getElementById('voucherSelect'); }

  // ===== Client resolver =====
  function getResolvedClientId(){
    if (!isBaseHost()){
      const parts = location.hostname.split('.');
      return (parts.length > 3 && parts[0]) ? sanitizeClientId(parts[0]) : '';
    }
    return selClient && selClient.value ? sanitizeClientId(selClient.value) : '';
  }

  // ===== Validasi sederhana =====
  function errElFor(input){ const id=(input?.id||'').replace(/^fld/,'err'); return document.getElementById(id); }
  function showFieldError(input,msg){
    const err=errElFor(input); if(!err||!input) return;
    if(msg){ err.textContent=msg; err.classList.remove('hidden'); input.classList.add('border-red-500','focus:ring-red-200'); input.setAttribute('aria-invalid','true'); }
    else   { err.textContent='';  err.classList.add('hidden');   input.classList.remove('border-red-500','focus:ring-red-200'); input.removeAttribute('aria-invalid'); }
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
    let norm=digits; if(plus62) norm='62'+digits.slice(2); else if(digits.startsWith('62')) norm=digits; else if(digits.startsWith('0')) norm='62'+digits.slice(1);
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

  // ===== Ringkasan =====
  function currentMethod(){
    const r=document.querySelector('.pm-radio:checked');
    return (r && (r.value||'').toUpperCase()) || 'QRIS';
  }
  function updateSummary(){
    const vSel = getVoucherSel();
    const opt  = vSel ? vSel.options[vSel.selectedIndex] : null;
    const name = opt ? (opt.getAttribute('data-name') || opt.textContent || '—') : '—';
    const price= opt ? parseInt(opt.getAttribute('data-price') || 0,10) : 0;
    if (sumName)   sumName.textContent  = name;
    if (sumTotal)  sumTotal.textContent = rupiah(price);
    if (sumMethod) sumMethod.textContent= currentMethod();
  }

  // ===== Voucher AJAX =====
  function rebuildVouchers(list){
    const vSel = document.getElementById('voucherSelect');
    if (!vSel) return;

    vSel.innerHTML = '';

    if (!Array.isArray(list) || list.length === 0){
      // kosong → kunci & ringkasan reset
      vSel.disabled = true;
      if (payBtn) payBtn.disabled = true;
      if (noVoucher) noVoucher.classList.remove('hidden');
      if (sumName)  sumName.textContent  = '—';
      if (sumTotal) sumTotal.textContent = 'Rp0';
      return;
    }

    if (noVoucher) noVoucher.classList.add('hidden');

    // placeholder supaya tidak auto-pilih
    const ph = document.createElement('option');
    ph.value = '';
    ph.textContent = '— Pilih Voucher —';
    ph.disabled = true;
    ph.selected = true;
    vSel.appendChild(ph);

    // tambahkan opsi voucher
    for (const v of list){
      const o = document.createElement('option');
      o.value = v.id;
      o.textContent = `${v.name} — ${rupiah(v.price)}`;
      o.setAttribute('data-name', v.name);
      o.setAttribute('data-price', v.price);
      vSel.appendChild(o);
    }

    vSel.disabled = false;
    if (payBtn) payBtn.disabled = true; // tetap terkunci sampai user pilih
    // ringkasan tetap Rp0/— sampai user memilih
  }

  async function fetchVouchers(cid){
    const vSel = getVoucherSel();
    try{
      if (hidClient) hidClient.value = cid;
      if (vSel){ vSel.disabled = true; vSel.innerHTML = '<option>Memuat…</option>'; }
      if (payBtn) payBtn.disabled = true;

      let res = await fetch(`${API_VOUCHERS_URL}?client=${encodeURIComponent(cid)}&client_id=${encodeURIComponent(cid)}`, { headers:{'Accept':'application/json'} });
      if (!res.ok && (res.status===404 || res.status===405)){
        res = await fetch(API_VOUCHERS_URL, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify({ client: cid, client_id: cid }) });
      }
      if (!res.ok) throw new Error('HTTP '+res.status);

      const json = await res.json();
      const list = Array.isArray(json) ? json : (json.data || json.vouchers || json.items || []);
      rebuildVouchers(list || []);
    }catch(e){
      console.error('Gagal memuat voucher:', e);
      rebuildVouchers([]);
    }
  }

  // ===== Init =====
  document.addEventListener('DOMContentLoaded', function(){
    // Base host tanpa pilihan → kunci UI
    if (isBaseHost() && (!selClient || !selClient.value)){
      if (hidClient) hidClient.value = '';
      const vSel = getVoucherSel();
      if (vSel){ vSel.disabled = true; vSel.innerHTML=''; }
      if (noVoucher) noVoucher.classList.remove('hidden');
      if (payBtn) payBtn.disabled = true;
    } else {
      // Subdomain atau server sudah pilih client
      const cid = getResolvedClientId();
      if (hidClient) hidClient.value = cid;
    }

    // Bersihkan onchange lama agar tidak reload
    if (selClient){
      // pastikan tidak reload halaman
      if (selClient.hasAttribute('onchange')) selClient.removeAttribute('onchange');
      selClient.onchange = null;

      selClient.addEventListener('change', function(e){
        e.preventDefault();
        e.stopPropagation();

        const newCid = selClient.value ? sanitizeClientId(selClient.value) : '';

        if (!newCid){
          // kembali ke kondisi awal: voucher kosong
          if (hidClient) hidClient.value = '';
          const vSel = document.getElementById('voucherSelect');
          if (vSel){ vSel.disabled = true; vSel.innerHTML = ''; }
          if (payBtn) payBtn.disabled = true;
          if (noVoucher) { noVoucher.textContent = 'Pilih client untuk menampilkan voucher.'; noVoucher.classList.remove('hidden'); }
          if (sumName)  sumName.textContent  = '—';
          if (sumTotal) sumTotal.textContent = 'Rp0';

          const url = new URL(location.href);
          url.searchParams.delete('client');
          history.replaceState(null, '', url.toString());
          return false;
        }

        if (hidClient) hidClient.value = newCid;
        if (noVoucher) { noVoucher.textContent = 'Memuat voucher…'; noVoucher.classList.remove('hidden'); }

        // muat voucher utk client terpilih
        fetchVouchers(newCid);

        // update URL (tanpa reload)
        const url = new URL(location.href);
        url.searchParams.set('client', newCid);
        history.replaceState(null, '', url.toString());
        return false;
      }, true);
    }

    // saat ganti voucher → enable tombol & update ringkasan
    const vSelInit = document.getElementById('voucherSelect');
    if (vSelInit){
      vSelInit.addEventListener('change', function(){
        const hasValue = !!this.value;
        if (payBtn) payBtn.disabled = !hasValue;
        updateSummary();
      });
      // sinkron state awal (kalau disabled/placeholder)
      if (payBtn) payBtn.disabled = !(vSelInit.value);
      updateSummary();
    }

    // Validasi realtime
    if (nameInput){
      nameInput.addEventListener('input', ()=>showFieldError(nameInput, validateName(nameInput)));
      nameInput.addEventListener('blur',  ()=>showFieldError(nameInput, validateName(nameInput)));
    }
    if (phoneInput){
      phoneInput.addEventListener('input', ()=>showFieldError(phoneInput, validatePhone(phoneInput).msg));
      phoneInput.addEventListener('blur',  ()=>showFieldError(phoneInput, validatePhone(phoneInput).msg));
    }

    // Ringkasan awal & listener voucher
    const vSel = getVoucherSel();
    if (vSel) vSel.addEventListener('change', updateSummary);
    updateSummary();

    // Toggle metode pembayaran (untuk update metode di ringkasan)
    function setChecked(card){
      $$('.pm-card').forEach(c=>{
        c.setAttribute('aria-checked','false');
        const r=c.querySelector('.pm-radio'); if(r) r.checked=false;
      });
      card.setAttribute('aria-checked','true');
      const radio=card.querySelector('.pm-radio'); if(radio) radio.checked=true;
      updateSummary();
    }
    const current=document.querySelector('.pm-radio:checked');
    if (current){ const card=current.closest('.pm-card'); if(card) setChecked(card); }
    $$('.pay-methods').forEach(group=>{
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

  // ===== Submit checkout =====
  window.startCheckout = async function(e){
    e.preventDefault();
    if (errBox){ errBox.classList.add('hidden'); errBox.textContent=''; }

    const cid = getResolvedClientId();
    if (isBaseHost() && !cid){
      if (errBox){ errBox.textContent = 'Silakan pilih client terlebih dahulu.'; errBox.classList.remove('hidden'); }
      return false;
    }

    const nmErr = validateName(nameInput); if (nmErr){ showFieldError(nameInput, nmErr); return false; } else { showFieldError(nameInput, null); }
    const { msg: phErr, norm: phoneNorm } = validatePhone(phoneInput); if (phErr){ showFieldError(phoneInput, phErr); return false; } else { showFieldError(phoneInput, null); }

    const vSel = getVoucherSel();
    if (!vSel || vSel.disabled || !vSel.value){
      if (errBox){ errBox.textContent = 'Silakan pilih voucher terlebih dahulu.'; errBox.classList.remove('hidden'); }
      return false;
    }

    if (hidClient) hidClient.value = cid;

    const payload = {
      voucher_id: Number(vSel.value),
      method: (document.querySelector('.pm-radio:checked')?.value || 'qris').toLowerCase(),
      name: String(nameInput.value||'').trim(),
      email: emailInput ? (emailInput.value||null) : null,
      phone: phoneNorm || String(phoneInput.value||'').trim(),
      client_id: cid,
    };

    setLoading(payBtn, true, 'Memproses…');

    const ctrl = new AbortController();
    const timer = setTimeout(()=>ctrl.abort(), 20000);

    try{
      const res = await fetch(API_CHECKOUT_URL, {
        method:'POST',
        headers:{ 'Content-Type':'application/json','Accept':'application/json' },
        body: JSON.stringify(payload),
        signal: ctrl.signal
      });
      const text = await res.text();
      let data; try{ data = JSON.parse(text); }catch{ throw new Error('RESP_INVALID: ' + text.slice(0,120)); }

      if (!res.ok){
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
      location.href = '/hotspot/order/' + encodeURIComponent(orderId);
      return false;

    }catch(e){
      setLoading(payBtn, false);
      if (errBox){
        errBox.textContent = (e.name === 'AbortError')
          ? 'Koneksi lambat atau server tidak merespons. Coba lagi.'
          : (e.message || 'Terjadi kesalahan.');
        errBox.classList.remove('hidden');
      }
      return false;
    }finally{
      clearTimeout(timer);
    }
  };
})();
</script>
@endpush
