@extends('layouts.app')
@section('title', 'Order Hotspot')

@section('content')
<div class="max-w-xl mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-3">Pembayaran</h1>

  <div id="status" class="text-sm text-gray-700 mb-4">Memuat…</div>

  {{-- QRIS box --}}
  <div id="qrisBox" class="border rounded p-4 mb-4 hidden">
    <h2 class="font-medium mb-3">Scan QRIS</h2>
    <img id="qrisImg" class="mx-auto border rounded" alt="QRIS" width="256" height="256" />
    <p id="qrisErr" class="text-xs text-red-600 mt-2 hidden">QRIS belum tersedia/expired.</p>
    <div class="mt-2">
      <button id="refreshQris" type="button" class="btn btn--ghost">Refresh QR</button>
    </div>
  </div>

  {{-- E-Money box (GoPay / ShopeePay) --}}
  <div id="ewalletBox" class="border rounded p-4 mb-4 hidden">
    <h2 id="ewalletTitle" class="font-medium mb-2">Bayar dengan E-Money</h2>

    <div class="flex items-center gap-2 mb-3">
      <button id="openPayBtn" type="button" class="btn btn--primary">
        <span class="btn__label">Buka pembayaran</span>
      </button>

      <button id="copyLink" type="button" class="btn btn--ghost">
        <span class="btn__label">Copy link</span>
        <svg id="copyCheck" class="icon-check hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20 6L9 17l-5-5"></path>
        </svg>
      </button>
    </div>

    {{-- QR hanya untuk GoPay --}}
    <img id="gopayQr" class="mx-auto border rounded hidden" alt="GoPay QR" width="256" height="256" />
    <p id="ewalletHint" class="text-xs text-gray-600 mt-2 hidden"></p>
    <p id="ewalletErr" class="text-xs text-red-600 mt-2 hidden">Link pembayaran tidak tersedia.</p>
  </div>

  {{-- Kredensial hotspot (muncul setelah paid) --}}
  <div id="credBox" class="rounded border p-3 hidden">
    <h2 class="font-medium mb-2">Akun Hotspot Kamu</h2>
    <p>Username: <code id="u"></code></p>
    <p>Password: <code id="p"></code></p>
  </div>
</div>

{{-- ikon ceklis minimalis (kalau belum ada di layout) --}}
<style>
  .icon-check{ width:1rem; height:1rem; color:#10b981; }
  .hidden{ display:none }
</style>
@endsection

@push('scripts')
<script>
(function(){
  function ready(fn){ if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);} else { fn(); } }

  ready(function(){
    const orderId = @json($orderId);
    const statusEl = document.getElementById('status');

    // QRIS refs
    const qrisBox = document.getElementById('qrisBox');
    const qrisImg = document.getElementById('qrisImg');
    const qrisErr = document.getElementById('qrisErr');
    const refreshQris = document.getElementById('refreshQris');

    // E-wallet refs
    const eBox  = document.getElementById('ewalletBox');
    const ttl   = document.getElementById('ewalletTitle');
    const openBtn = document.getElementById('openPayBtn');
    const copyBtn = document.getElementById('copyLink');
    const copyCheck = document.getElementById('copyCheck');
    const hint  = document.getElementById('ewalletHint');
    const err   = document.getElementById('ewalletErr');
    const gopayQr = document.getElementById('gopayQr');

    let deeplinkUrl = null;

    // tombol: open tanpa loading
    if (openBtn) openBtn.addEventListener('click', function(){
      if (!deeplinkUrl){ err.classList.remove('hidden'); return; }
      window.open(deeplinkUrl, '_blank', 'noopener');
    });

    // tombol: copy dengan ceklis hijau
    if (copyBtn) copyBtn.addEventListener('click', async function(){
      if (!deeplinkUrl){ err.classList.remove('hidden'); return; }
      const label = copyBtn.querySelector('.btn__label');
      const old = label ? label.textContent : null;
      try{
        await navigator.clipboard.writeText(deeplinkUrl);
        if (label) label.textContent = 'Tersalin';
        if (copyCheck) copyCheck.classList.remove('hidden');
        hint.textContent = 'Link disalin ke clipboard.'; hint.classList.remove('hidden'); err.classList.add('hidden');
        setTimeout(()=>{ if(label&&old!==null) label.textContent = old; if(copyCheck) copyCheck.classList.add('hidden'); }, 1200);
      }catch(e){
        err.textContent = 'Gagal menyalin link.'; err.classList.remove('hidden');
      }
    });

    // refresh QRIS
    if (refreshQris) refreshQris.addEventListener('click', function(){
      if (!qrisImg) return;
      qrisErr.classList.add('hidden');
      qrisImg.src = '/api/payments/'+orderId+'/qris.png?ts=' + Date.now();
    });

    async function loadPayment(){
      const r = await fetch('/api/payments/'+orderId, {headers:{'Accept':'application/json'}});
      if(!r.ok){ statusEl.textContent = 'Gagal memuat status'; return; }
      const d = await r.json();

      const payType = String((d.raw && d.raw.payment_type) || '').toLowerCase();
      const kind = String(d.kind || '').toLowerCase();
      const acts = d.actions ? d.actions : (d.raw && d.raw.actions ? d.raw.actions : null);

      // --- QRIS ---
      const isQris = (payType === 'qris') || (kind === 'qris') || !!d.qr_string;
      if (isQris){
        // tampilkan QRIS, sembunyikan e-wallet
        qrisBox.classList.remove('hidden');
        eBox.classList.add('hidden');

        // load QRIS png dari server
        qrisErr.classList.add('hidden');
        qrisImg.onload = function(){ qrisErr.classList.add('hidden'); };
        qrisImg.onerror = function(){ qrisErr.classList.remove('hidden'); };
        // set ulang setiap loadPayment (cache-buster)
        qrisImg.src = '/api/payments/'+orderId+'/qris.png?ts=' + Date.now();
      } else {
        // --- E-wallet (GoPay / ShopeePay) ---
        eBox.classList.remove('hidden');
        qrisBox.classList.add('hidden');

        let deep=null, web=null, hasQr=false;
        if (Array.isArray(acts)){
          for (const a of acts){
            const name = String(a.name||'').toLowerCase();
            if (['deeplink-redirect','mobile_deeplink_checkout_url'].includes(name)) deep = a.url;
            if (['desktop_web_checkout_url','web_checkout'].includes(name)) web = a.url;
            if (['generate-qr-code','generate-qr-code-v2','qr_checkout'].includes(name)) hasQr = true;
          }
        } else if (acts && typeof acts === 'object') {
          deep  = acts.deeplink_url || null;
          web   = acts.web_checkout_url || null;
          hasQr = !!acts.qr_code_url;
        }

        deeplinkUrl = web || deep || null;

        if (String(payType) === 'shopeepay'){
          ttl.textContent = 'Bayar dengan ShopeePay';
          hint.textContent = 'Klik “Buka pembayaran” untuk ke Simulator ShopeePay.'; hint.classList.remove('hidden');
          gopayQr.classList.add('hidden');
        } else {
          ttl.textContent = 'Bayar dengan GoPay';
          if (hasQr){
            hint.classList.add('hidden');
            gopayQr.onload  = function(){ gopayQr.classList.remove('hidden'); };
            gopayQr.onerror = function(){ err.classList.remove('hidden'); gopayQr.classList.add('hidden'); };
            gopayQr.src = '/api/payments/'+orderId+'/ewallet/qr?ts=' + Date.now();
          } else {
            gopayQr.classList.add('hidden');
          }
        }

        if (!deeplinkUrl && !hasQr){
          err.classList.remove('hidden');
        } else {
          err.classList.add('hidden');
        }
      }

      statusEl.textContent = 'Status: ' + (d.status || d.transaction_status || 'pending');

      // kalau sudah paid, minta kredensial
      if ((d.status||'').toUpperCase() === 'PAID') {
        try {
          const r2 = await fetch('/api/hotspot/credentials/'+orderId, {headers:{'Accept':'application/json'}});
          const c = await r2.json();
          if (c.ready){
            document.getElementById('credBox').classList.remove('hidden');
            document.getElementById('u').textContent = c.username;
            document.getElementById('p').textContent = c.password;
            statusEl.textContent = 'Akun siap dipakai.';
          }
        } catch(e){}
      }
    }

    // pertama & polling ringan
    loadPayment();
    setInterval(loadPayment, 5000);
  });
})();
</script>
@endpush
