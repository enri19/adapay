@extends('layouts.app')
@section('title', 'Order Hotspot')

@push('head')
<style>
  /* ====== Box/Panel (selaras dengan index) ====== */
  .panel{border:1px solid #e5e7eb;border-radius:1rem;background:#fff;padding:1rem}
  .panel--accent{background:linear-gradient(180deg,#f8fbff, #fff)}
  .panel-hd{display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem}
  .panel-ic{width:20px;height:20px;color:#0284c7}
  .subcard{border:1px solid #eef2f7;border-radius:.75rem;background:#fff}
  .subcard-hd{padding:.75rem .9rem;border-bottom:1px solid #eef2f7;font-weight:600}
  .subcard-bd{padding:.9rem}

  /* ====== Payment helpers (konsisten) ====== */
  .summary{border:1px solid #dbeafe;background:#f0f7ff;border-radius:.75rem;padding:.75rem}
  .summary-row{display:flex;justify-content:space-between;gap:.75rem;font-size:.92rem}
  .summary-row + .summary-row{margin-top:.25rem}
  .summary-total{font-weight:700}
  .muted{color:#6b7280}
  .icon-check{width:1rem;height:1rem;color:#10b981}
  .hidden{display:none}

  /* Gambar QR */
  .qr-img{display:block;margin:0 auto;border:1px solid #e5e7eb;border-radius:.5rem;max-width:256px}
</style>
@endpush

@section('content')
<div class="max-w-xl mx-auto p-1">
  <div class="panel panel--accent">
    <div class="panel-hd">
      <svg class="panel-ic" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm1 15h-2v-2h2Zm0-4h-2V7h2Z"/></svg>
      <div>
        <h1 class="text-xl font-semibold leading-tight">Pembayaran</h1>
        <p class="text-sm muted">Selesaikan pembayaran, lalu kredensial hotspot akan muncul otomatis.</p>
      </div>
    </div>

    {{-- Status Pembayaran --}}
    <div class="subcard mb-3">
      <div class="subcard-hd">Status Pembayaran</div>
      <div class="subcard-bd">
        <div id="status" class="text-sm text-gray-700">Memuat…</div>
      </div>
    </div>

    {{-- QRIS box --}}
    <div id="qrisBox" class="subcard mb-3 hidden">
      <div class="subcard-hd">Scan QRIS</div>
      <div class="subcard-bd">
        <img id="qrisImg" class="qr-img" alt="QRIS" width="256" height="256" />
        <p id="qrisErr" class="text-xs text-red-600 mt-2 hidden">QRIS belum tersedia/expired.</p>
        <div class="mt-2">
          <button id="refreshQris" type="button" class="btn btn--ghost">Refresh QR</button>
        </div>
      </div>
    </div>

    {{-- E-Money box (GoPay / ShopeePay) --}}
    <div id="ewalletBox" class="subcard mb-3 hidden">
      <div class="subcard-hd"><span id="ewalletTitle">Bayar dengan E-Money</span></div>
      <div class="subcard-bd">
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
        <img id="gopayQr" class="qr-img hidden" alt="GoPay QR" width="256" height="256" />
        <p id="ewalletHint" class="text-xs text-gray-600 mt-2 hidden"></p>
        <p id="ewalletErr" class="text-xs text-red-600 mt-2 hidden">Link pembayaran tidak tersedia.</p>
      </div>
    </div>

    {{-- Kredensial hotspot (muncul setelah paid) --}}
    <div id="credBox" class="subcard hidden">
      <div class="subcard-hd">Akun Hotspot Kamu</div>
      <div class="subcard-bd">
        <p>Username: <code id="u"></code></p>
        <p>Password: <code id="p"></code></p>
        <p class="text-xs text-gray-600 mt-2">
          @verbatim
          <span id="hintMode"></span>
          @endverbatim
        </p>
      </div>
    </div>

    {{-- Catatan --}}
    <div class="summary mt-3">
      <div class="summary-row">
        <span class="muted">Tips</span>
        <span class="text-right">Jika QR/tautan tidak muncul, klik “Refresh QR” atau “Buka pembayaran”.</span>
      </div>
    </div>
  </div>
</div>
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
    let pollId = null;
    let finished = false;

    function stopPolling(){
      if (pollId){ clearInterval(pollId); pollId = null; }
    }
    function lockPaymentUi(){
      refreshQris && refreshQris.setAttribute('disabled','disabled');
      openBtn && openBtn.setAttribute('disabled','disabled');
      copyBtn && copyBtn.setAttribute('disabled','disabled');
    }

    if (openBtn) openBtn.addEventListener('click', function(){
      if (!deeplinkUrl || finished){ err.classList.remove('hidden'); return; }
      window.open(deeplinkUrl, '_blank', 'noopener');
    });

    if (copyBtn) copyBtn.addEventListener('click', async function(){
      if (!deeplinkUrl || finished){ err.classList.remove('hidden'); return; }
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

    if (refreshQris) refreshQris.addEventListener('click', function(){
      if (finished) return;
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

      const norm = String(d.status || d.transaction_status || '').toUpperCase();
      const rawTx = String(d.raw && d.raw.transaction_status || '').toLowerCase();
      const isPaidish = norm === 'PAID' || ['settlement','capture','success'].includes(rawTx);

      // QRIS
      const isQris = (payType === 'qris') || (kind === 'qris') || !!d.qr_string;
      if (isQris){
        qrisBox.classList.remove('hidden');
        eBox.classList.add('hidden');

        if (!finished){
          qrisErr.classList.add('hidden');
          qrisImg.onload = function(){ qrisErr.classList.add('hidden'); };
          qrisImg.onerror = function(){ qrisErr.classList.remove('hidden'); };
          qrisImg.src = '/api/payments/'+orderId+'/qris.png?ts=' + Date.now();
        }
      } else {
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
          if (hasQr && !finished){
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

      statusEl.textContent = 'Status: ' + (norm || 'PENDING');

      if (isPaidish && !finished) {
        finished = true;
        stopPolling();
        lockPaymentUi();
        try {
          const r2 = await fetch('/api/hotspot/credentials/'+orderId, {headers:{'Accept':'application/json'}});
          const c = await r2.json();
          if (c && c.ready){
            document.getElementById('credBox').classList.remove('hidden');
            document.getElementById('u').textContent = c.username;
            document.getElementById('p').textContent = (c.mode === 'code') ? '(sama dengan kode)' : c.password;
            statusEl.textContent = 'Akun siap dipakai.' + (c.mode === 'code'
              ? ' Gunakan kode sebagai USERNAME dan PASSWORD di halaman login.'
              : ''
            );

            if (c.mode === 'code') {
              document.getElementById('hintMode').textContent =
                'Masukkan kode pada kolom Voucher. Jika halaman meminta Username & Password, isi keduanya dengan kode di atas.';
            } else {
              document.getElementById('hintMode').textContent =
                'Masukkan sesuai yang tertera.';
            }
          }
        } catch(e){}
      }
    }

    loadPayment();
    pollId = setInterval(loadPayment, 5000);
  });
})();
</script>
@endpush
