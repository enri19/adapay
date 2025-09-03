@extends('layouts.app')
@section('title', 'Status Pembayaran')

@push('head')
<style>
  /* ====== Panel & Subcard (selaras dengan index/order) ====== */
  .panel{border:1px solid #e5e7eb;border-radius:1rem;background:#fff;padding:1rem}
  .panel--accent{background:linear-gradient(180deg,#f8fbff, #fff)}
  .panel-hd{display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem}
  .panel-ic{width:20px;height:20px;color:#0284c7}
  .subcard{border:1px solid #eef2f7;border-radius:.75rem;background:#fff}
  .subcard-hd{padding:.75rem .9rem;border-bottom:1px solid #eef2f7;font-weight:600}
  .subcard-bd{padding:.9rem}
  .summary{border:1px solid #dbeafe;background:#f0f7ff;border-radius:.75rem;padding:.75rem}
  .summary-row{display:flex;justify-content:space-between;gap:.75rem;font-size:.92rem}
  .summary-row + .summary-row{margin-top:.25rem}
  .summary-total{font-weight:700}
  .muted{color:#6b7280}
  .code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}
  .ic{width:18px;height:18px}
  .hidden{display:none}

  /* ===== Smart Loader (asli, dipertahankan) ===== */
  #smart-loader{
    position:fixed; inset:0; z-index:60; display:none;
    align-items:center; justify-content:center;
    background:linear-gradient(135deg, rgba(15,23,42,.7), rgba(2,6,23,.7));
    backdrop-filter: blur(6px) saturate(120%);
  }
  #smart-loader.is-visible{ display:flex; }
  .loader-card{
    width:min(560px, 92vw);
    background:rgba(255,255,255,.94);
    border:1px solid rgba(0,0,0,.08);
    border-radius:16px;
    box-shadow: 0 20px 65px rgba(0,0,0,.25);
    overflow:hidden;
  }
  .loader-head{
    padding:18px 18px 14px; display:flex; align-items:center; gap:12px;
    border-bottom:1px solid rgba(0,0,0,.06);
    background:linear-gradient(180deg, rgba(255,255,255,1), rgba(255,255,255,.7));
  }
  .spin{
    width:26px;height:26px;border-radius:999px; border:3px solid rgba(0,0,0,.18); border-top-color:#2563eb;
    animation:spin .8s linear infinite;
  }
  @keyframes spin{to{transform:rotate(360deg)}}
  .loader-title{ font-weight:700; font-size:1rem; color:#0f172a; }
  .loader-sub{ font-size:.85rem; color:#475569; }
  .loader-body{ padding:16px 18px 18px; }
  .steps{ list-style:none; margin:0; padding:0; display:grid; gap:10px; }
  .step{
    display:grid; grid-template-columns: 24px 1fr; gap:10px; align-items:flex-start;
    padding:10px 12px; border-radius:12px; background:#fff; border:1px solid rgba(0,0,0,.06);
  }
  .step .dot{
    width:20px;height:20px;border-radius:50%;
    background:#e5e7eb; display:flex;align-items:center;justify-content:center;
    font-size:12px; color:#fff;
  }
  .step .txt{ line-height:1.2; color:#0f172a; font-weight:600; }
  .step .help{ color:#64748b; font-size:.82rem; margin-top:2px; }
  .step.is-active{ border-color:#93c5fd; background:linear-gradient(180deg,#fff,#f8fbff); }
  .step.is-active .dot{ background:#2563eb; box-shadow:0 0 0 4px rgba(37,99,235,.12); }
  .step.is-done{ opacity:.85; }
  .step.is-done .dot{ background:#10b981; }
  .step.is-done .dot::before{ content:'✓'; font-weight:700; transform:translateY(-1px); }
  .progress{ height:6px; border-radius:999px; background:#e5e7eb; overflow:hidden; margin-top:12px; }
  .progress > i{ display:block; height:100%; width:0%; background:linear-gradient(90deg,#60a5fa,#2563eb); transition:width .3s ease; }
  .loader-foot{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding:12px 18px 14px; border-top:1px solid rgba(0,0,0,.06); background:#fafafa;
    font-size:.85rem; color:#475569;
  }
  .loader-foot .muted{ opacity:.8; }
  .loader-foot .actions{ display:flex; gap:8px; }
  .btn-min{
    padding:6px 10px; border-radius:10px; background:#111827; color:#fff; font-weight:600; font-size:.8rem;
  }
  .btn-ghost{
    padding:6px 10px; border-radius:10px; background:#fff; border:1px solid #e5e7eb; color:#111827; font-weight:600; font-size:.8rem;
  }
  @media (max-width:420px){
    .loader-title{ font-size:.95rem }
    .loader-head{ padding:14px 14px 10px }
    .loader-body{ padding:12px 14px 14px }
    .loader-foot{ padding:10px 14px }
  }
</style>
@endpush

@section('content')
<div class="max-w-xl mx-auto p-1">
  <div class="panel panel--accent">
    <div class="panel-hd">
      <svg class="panel-ic" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm1 15h-2v-2h2Zm0-4h-2V7h2Z"/></svg>
      <div>
        <h1 class="text-xl font-semibold leading-tight">Status Pembayaran</h1>
        <p class="text-sm muted">Pantau status & ambil kredensial hotspot kamu di sini.</p>
      </div>
    </div>

    @if(!$orderId)
      <div class="subcard">
        <div class="subcard-bd text-sm text-red-600">Order ID tidak ditemukan.</div>
      </div>
    @else
      <div class="subcard mb-3">
        <div class="subcard-hd">Informasi Order</div>
        <div class="subcard-bd">
          <p class="text-sm">Order ID: <strong class="code">{{ $orderId }}</strong></p>
        </div>
      </div>

      @php
        $authMode  = isset($authMode) ? strtolower((string)$authMode) : null;
        $u         = is_array($creds ?? null) ? ($creds['u'] ?? null) : null;
        $p         = is_array($creds ?? null) ? ($creds['p'] ?? null) : null;
        $infer     = ($u && $p && strtoupper($u) === strtoupper($p)) ? 'code' : 'userpass';
        $mode      = in_array($authMode, ['code','userpass'], true) ? $authMode : $infer;
        $portalUrl = $hotspotPortal ?? config('hotspot.portal_default');

        $showLoader   = $orderId && ( ($status === 'PENDING') || ($status === 'PAID' && empty($creds)) );
        $initialStep  = $status === 'PENDING' ? 1 : ( ($status === 'PAID' && empty($creds)) ? 2 : 0 );
        $initialPct   = $initialStep ? max(0, min(100, ($initialStep-1) * 33)) : 0;
      @endphp

      @if($status === 'PAID')
        <div class="subcard mb-3">
          <div class="subcard-hd">Pembayaran</div>
          <div class="subcard-bd">
            <div class="rounded border border-green-200 bg-green-50 p-3 mb-2">
              Pembayaran <strong>berhasil</strong>.
            </div>

            @if($creds)
              <div class="summary mb-2">
                <div class="summary-row"><span>Status</span><span class="summary-total">PAID</span></div>
              </div>

              <div class="subcard">
                <div class="subcard-hd">Akun Hotspot Kamu</div>
                <div class="subcard-bd">
                  @if($mode === 'code')
                    <p class="flex items-center gap-2">
                      <span>Kode Voucher:</span>
                      <code id="cred-code" class="code">{{ strtoupper($creds['u']) }}</code>
                      <button type="button" class="copy-btn js-copy" data-copy="cred-code" aria-label="Salin kode">
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <svg class="ic ic-ok hidden" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M20 6L9 17l-5-5"></path>
                        </svg>
                      </button>
                    </p>
                    <p class="text-xs text-gray-600 mt-1">
                      Gunakan <strong>kode yang sama</strong> untuk kolom <em>Username</em> & <em>Password</em>.
                    </p>
                  @else
                    <p class="flex items-center gap-2">
                      <span>Username:</span>
                      <code id="cred-user" class="code">{{ strtoupper($creds['u']) }}</code>
                      <button type="button" class="copy-btn js-copy" data-copy="cred-user" aria-label="Salin username">
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <svg class="ic ic-ok hidden" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M20 6L9 17l-5-5"></path>
                        </svg>
                      </button>
                    </p>
                    <p class="flex items-center gap-2">
                      <span>Password:</span>
                      <code id="cred-pass" class="code">{{ strtoupper($creds['p']) }}</code>
                      <button type="button" class="copy-btn js-copy" data-copy="cred-pass" aria-label="Salin password">
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <svg class="ic ic-ok hidden" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M20 6L9 17l-5-5"></path>
                        </svg>
                      </button>
                    </p>
                  @endif

                  @if(!empty($portalUrl))
                    <div class="mt-3">
                      <a href="{{ $portalUrl }}" target="_blank" rel="noopener"
                         class="inline-flex items-center gap-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 px-3 py-2 text-sm font-medium">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                          <path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M21 14v7H3V3h7"></path>
                        </svg>
                        Buka Halaman Login Hotspot
                      </a>
                      <p class="mt-1 text-xs text-gray-500">Pastikan perangkat sudah tersambung ke Wi-Fi hotspot.</p>
                    </div>
                  @endif
                </div>
              </div>
            @else
              <div class="text-sm">Menyiapkan akun hotspot…</div>
            @endif
          </div>
        </div>
      @elseif($status === 'PENDING')
        <div class="subcard">
          <div class="subcard-hd">Pembayaran</div>
          <div class="subcard-bd">
            <div class="rounded border border-yellow-200 bg-yellow-50 p-3">Menunggu pembayaran…</div>
          </div>
        </div>
      @else
        <div class="subcard">
          <div class="subcard-hd">Pembayaran</div>
          <div class="subcard-bd">
            <div class="rounded border p-3">Status: {{ $status }}</div>
          </div>
        </div>
      @endif

      <div class="summary mt-3">
        <div class="summary-row">
          <span class="muted">Navigasi</span>
          <span class="text-right">
            <a class="text-blue-600 underline" href="{{ route('hotspot.order', ['orderId'=>$orderId]) }}">Kembali ke halaman order</a>
          </span>
        </div>
      </div>
    @endif
  </div>
</div>

{{-- SMART LOADER OVERLAY (HTML tetap, JS tetap) --}}
@if($orderId)
<div id="smart-loader" class="{{ $showLoader ? 'is-visible' : '' }}" aria-live="polite" aria-busy="{{ $showLoader ? 'true':'false' }}">
  <div class="loader-card">
    <div class="loader-head">
      <div class="spin" aria-hidden="true"></div>
      <div>
        <div class="loader-title">Memproses pembayaran & menyiapkan akun…</div>
        <div class="loader-sub">Order ID: <span class="code">{{ $orderId }}</span></div>
      </div>
    </div>

    <div class="loader-body">
      <ul class="steps">
        <li class="step {{ $initialStep > 1 ? 'is-done' : ($initialStep === 1 ? 'is-active' : '') }}" data-step="1">
          <div class="dot" aria-hidden="true"></div>
          <div><div class="txt">Cek status pembayaran</div><div class="help">Sinkron dengan Webhook Payment</div></div>
        </li>
        <li class="step {{ $initialStep > 2 ? 'is-done' : ($initialStep === 2 ? 'is-active' : '') }}" data-step="2">
          <div class="dot" aria-hidden="true"></div>
          <div><div class="txt">Siapkan akun hotspot</div><div class="help">Buat kredensial</div></div>
        </li>
        <li class="step {{ $initialStep > 3 ? 'is-done' : ($initialStep === 3 ? 'is-active' : '') }}" data-step="3">
          <div class="dot" aria-hidden="true"></div>
          <div><div class="txt">Dorong ke router</div><div class="help">Provision Router</div></div>
        </li>
        <li class="step {{ $initialStep > 4 ? 'is-done' : ($initialStep === 4 ? 'is-active' : '') }}" data-step="4">
          <div class="dot" aria-hidden="true"></div>
          <div><div class="txt">Kirim WhatsApp</div><div class="help">Kredensial dikirim ke nomor kamu</div></div>
        </li>
      </ul>

      <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $initialPct }}">
        <i style="width:{{ $initialPct }}%"></i>
      </div>
    </div>

    <div class="loader-foot">
      <div class="muted"><span id="elapsed">0s</span> berlalu</div>
      <div class="actions">
        <button type="button" class="btn-ghost" id="btn-refresh">Refresh</button>
        <a href="{{ route('hotspot.order', ['orderId'=>$orderId]) }}" class="btn-min">Halaman Order</a>
      </div>
    </div>
  </div>
</div>

<noscript>
  <div style="position:fixed;left:0;right:0;bottom:0;background:#fee2e2;color:#7f1d1d;padding:.6rem 1rem;text-align:center;font-size:.9rem">
    Aktifkan JavaScript untuk melihat progres otomatis.
  </div>
</noscript>
@endif
@endsection

@push('scripts')
{{-- JavaScript ASLI — TIDAK DIUBAH --}}
<script>
(function(){
  // --- Copy helpers (tetap) ---
  function copyTextById(id){
    var el = document.getElementById(id);
    if (!el) throw new Error('Target not found');
    var text = (el.textContent || '').trim();
    if (!text) throw new Error('Empty');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    var ta = document.createElement('textarea');
    ta.value = text; document.body.appendChild(ta);
    ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
    return Promise.resolve();
  }
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.js-copy');
    if (!btn) return;
    var target = btn.getAttribute('data-copy');
    if (!target) return;
    btn.setAttribute('disabled','disabled');
    copyTextById(target)
      .then(function(){
        var ok = btn.querySelector('.ic-ok');
        var ic = btn.querySelector('.ic:not(.ic-ok)');
        if (ok && ic){ ic.classList.add('hidden'); ok.classList.remove('hidden'); }
        setTimeout(function(){
          if (ok && ic){ ok.classList.add('hidden'); ic.classList.remove('hidden'); }
          btn.removeAttribute('disabled');
        }, 1000);
      })
      .catch(function(){
        btn.removeAttribute('disabled');
        alert('Gagal menyalin.');
      });
  });

  // --- Smart loader dengan grace time ---
  const ORDER_ID = @json($orderId);
  const CURRENT_STATUS = @json($status);
  const HAS_CREDS = Boolean(@json((bool) $creds));

  const LOADER = document.getElementById('smart-loader');
  const PROG = LOADER?.querySelector('.progress > i');
  const STEPS = LOADER?.querySelectorAll('.step');
  const ELAPSED = document.getElementById('elapsed');
  const BTN_REFRESH = document.getElementById('btn-refresh');

  const MIN_VISIBLE_MS = 1200;
  const PAID_GRACE_MS  = 1400;
  const POLL_INTERVAL  = 2000;
  const HARD_STOP_MS   = 120000;

  function shouldShowLoader(){
    return !!ORDER_ID && ((CURRENT_STATUS === 'PENDING') || (CURRENT_STATUS === 'PAID' && !HAS_CREDS));
  }

  function setStepState(activeIndex){
    if (!STEPS) return;
    STEPS.forEach((li, idx) => {
      const i = idx + 1;
      li.classList.remove('is-active','is-done');
      if (i < activeIndex) li.classList.add('is-done');
      else if (i === activeIndex) li.classList.add('is-active');
    });
    const pct = Math.min(100, Math.max(0, (activeIndex - 1) * 33));
    if (PROG){ PROG.style.width = pct + '%'; PROG.parentElement?.setAttribute('aria-valuenow', pct); }
  }

  let t0 = Date.now(), tickTmr = null, pollTmr = null;
  let paidSeen = false, reloadScheduled = false;

  function startElapsed(){
    if (!ELAPSED) return;
    t0 = Date.now();
    tickTmr = setInterval(()=>{
      const s = Math.floor((Date.now()-t0)/1000);
      ELAPSED.textContent = s + 's';
    }, 1000);
  }

  function scheduleReload(ms){
    if (reloadScheduled) return;
    reloadScheduled = true;
    setTimeout(()=>{ location.reload(); }, ms);
  }

  function hideLoader(){
    if (pollTmr) clearTimeout(pollTmr);
    if (tickTmr) clearInterval(tickTmr);
    LOADER?.classList.remove('is-visible');
  }

  function poll(){
    fetch('/api/payments/' + encodeURIComponent(ORDER_ID), {headers:{'Accept':'application/json'}})
      .then(r=>r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)))
      .then(data=>{
        const status = String(data.status || '').toUpperCase();

        if (status === 'PENDING'){
          setStepState(1);
        } else if (status === 'PAID'){
          setStepState(2);
          if (!paidSeen){
            paidSeen = true;
            setTimeout(()=>setStepState(3), 300);
            setTimeout(()=>setStepState(4), 700);
            const elapsed = Date.now() - t0;
            const wait = Math.max(0, MIN_VISIBLE_MS - elapsed) + PAID_GRACE_MS;
            scheduleReload(wait);
          }
        } else {
          hideLoader();
        }
      })
      .catch(()=>{})
      .finally(()=>{
        if (!LOADER?.classList.contains('is-visible')) return;
        if ((Date.now()-t0) > HARD_STOP_MS){ hideLoader(); return; }
        pollTmr = setTimeout(poll, POLL_INTERVAL);
      });
  }

  function showLoader(){
    if (!LOADER) return;
    LOADER.classList.add('is-visible');
    setStepState(CURRENT_STATUS === 'PENDING' ? 1 : 2);
    startElapsed();
    poll();
  }

  if (shouldShowLoader()) showLoader();
  BTN_REFRESH?.addEventListener('click', ()=> location.reload());
})();
</script>
@endpush
