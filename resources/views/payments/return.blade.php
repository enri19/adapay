@extends('layouts.app')
@section('title', 'Status Pembayaran')

@push('head')
<style>
/* ===== Smart Loader (polished) ===== */
#smart-loader{
  position:fixed; inset:0; z-index:9999;
  display:flex; align-items:center; justify-content:center;
  background:linear-gradient(135deg, rgba(15,23,42,.65), rgba(2,6,23,.65));
  backdrop-filter: blur(6px) saturate(120%);
  /* smoother show/hide tanpa FOUC */
  opacity:0; visibility:hidden; pointer-events:none; transition:opacity .18s ease, visibility .18s ease;
}
#smart-loader.is-visible{ opacity:1; visibility:visible; pointer-events:auto; }

.loader-card{
  width:min(560px, 92vw);
  background:rgba(255,255,255,.92);
  border:1px solid rgba(0,0,0,.08);
  border-radius:16px;
  box-shadow: 0 22px 65px rgba(0,0,0,.28);
  overflow:hidden;
  -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
}

.loader-head{
  padding:16px 18px 12px; display:flex; align-items:center; gap:12px;
  border-bottom:1px solid rgba(0,0,0,.06);
  background:linear-gradient(180deg, rgba(255,255,255,1), rgba(255,255,255,.75));
}
.spin{
  width:26px;height:26px;border-radius:999px; border:3px solid rgba(0,0,0,.18); border-top-color:#2563eb;
  animation:spin .8s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
.loader-title{ font-weight:700; font-size:1rem; color:#0f172a; }
.loader-sub{ font-size:.85rem; color:#475569; }
.mono, code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }

.loader-body{ padding:14px 18px 16px; }

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
.step .txt{ line-height:1.15; color:#0f172a; font-weight:600; }
.step .help{ color:#64748b; font-size:.82rem; margin-top:2px; }

.step.is-active{ border-color:#93c5fd; background:linear-gradient(180deg,#fff,#f8fbff); }
.step.is-active .dot{ background:#2563eb; box-shadow:0 0 0 4px rgba(37,99,235,.12); }
.step.is-done  { opacity:.85; }
.step.is-done .dot{ background:#10b981; }
.step.is-done .dot::before{ content:'✓'; font-weight:700; transform:translateY(-1px); }

.progress{
  height:6px; border-radius:999px; background:#e5e7eb; overflow:hidden; margin-top:12px;
}
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
@media (max-width: 420px){
  .loader-title{ font-size:.95rem; }
  .loader-head{ padding:14px 14px 10px; }
  .loader-body{ padding:12px 14px 14px; }
  .loader-foot{ padding:10px 14px; }
}
</style>
@endpush

@section('content')
<div class="max-w-xl mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-3">Status Pembayaran</h1>

  @if(!$orderId)
    <div class="text-sm text-red-600">Order ID tidak ditemukan.</div>
  @else
    <p class="text-sm mb-4">Order ID: <strong>{{ $orderId }}</strong></p>

    @php
      // --- tentukan mode: pakai $authMode jika disediakan controller, fallback deteksi (u==p) ---
      $authMode = isset($authMode) ? strtolower((string)$authMode) : null;   // 'code' | 'userpass' | null
      $u = is_array($creds ?? null) ? ($creds['u'] ?? null) : null;
      $p = is_array($creds ?? null) ? ($creds['p'] ?? null) : null;
      $infer = ($u && $p && strtoupper($u) === strtoupper($p)) ? 'code' : 'userpass';
      $mode = in_array($authMode, ['code','userpass'], true) ? $authMode : $infer;
      $portalUrl = $hotspotPortal
        ?? (isset($client) && $client ? ($client->hotspot_portal ?? null) : null)
        ?? config('hotspot.portal_default');
    @endphp

    @if($status === 'PAID')
      <div class="rounded border border-green-200 bg-green-50 p-3 mb-4">
        Pembayaran <strong>berhasil</strong>.
      </div>

      @if($creds)
        <div class="rounded border p-3">
          <h2 class="font-medium mb-2">Akun Hotspot Kamu</h2>

          @if($mode === 'code')
            <p class="flex items-center gap-2">
              <span>Kode Voucher:</span>
              <code id="cred-code">{{ strtoupper($creds['u']) }}</code>
              <!-- tombol copy -->
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
              Gunakan <strong>kode yang sama</strong> untuk kolom <em>Username</em> & <em>Password</em>, atau isi di kolom <em>Voucher</em> jika halaman login 1-kolom.
            </p>
          @else
            <p class="flex items-center gap-2">
              <span>Username:</span>
              <code id="cred-user">{{ strtoupper($creds['u']) }}</code>
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
              <code id="cred-pass">{{ strtoupper($creds['p']) }}</code>
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
        </div>

        {{-- Tombol ke halaman login hotspot --}}
        @if(!empty($portalUrl))
          <div class="mt-4">
            <a href="{{ $portalUrl }}"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 px-3 py-2 text-sm font-medium">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 3h6v6"></path>
                <path d="M10 14 21 3"></path>
                <path d="M21 14v7H3V3h7"></path>
              </svg>
              Buka Halaman Login Hotspot
            </a>
            <p class="mt-1 text-xs text-gray-500">Pastikan perangkat sudah tersambung ke Wi-Fi hotspot agar portal bisa diakses.</p>
          </div>
        @endif

      @else
        <div class="text-sm">Menyiapkan akun hotspot…</div>
      @endif

    @elseif($status === 'PENDING')
      <div class="rounded border border-yellow-200 bg-yellow-50 p-3 mb-4">
        Menunggu pembayaran…
      </div>
    @else
      <div class="rounded border p-3 mb-4">Status: {{ $status }}</div>
    @endif

    <div class="mt-4">
      <a class="text-blue-600 underline" href="{{ route('hotspot.order', ['orderId'=>$orderId]) }}">
        Kembali ke halaman order
      </a>
    </div>
  @endif
</div>

{{-- SMART LOADER OVERLAY --}}
<div id="smart-loader" aria-live="polite" aria-busy="true">
  <div class="loader-card">
    <div class="loader-head">
      <div class="spin" aria-hidden="true"></div>
      <div>
        <div class="loader-title">Memproses pembayaran & menyiapkan akun…</div>
        <div class="loader-sub">Order ID: <span class="mono">{{ $orderId }}</span></div>
      </div>
    </div>

    <div class="loader-body">
      <ul class="steps">
        <li class="step" data-step="1">
          <div class="dot" aria-hidden="true"></div>
          <div>
            <div class="txt">Cek status pembayaran</div>
            <div class="help">Sinkron dengan Midtrans</div>
          </div>
        </li>
        <li class="step" data-step="2">
          <div class="dot" aria-hidden="true"></div>
          <div>
            <div class="txt">Siapkan akun hotspot</div>
            <div class="help">Buat kredensial & dorong ke router</div>
          </div>
        </li>
        <li class="step" data-step="3">
          <div class="dot" aria-hidden="true"></div>
          <div>
            <div class="txt">Kirim WhatsApp</div>
            <div class="help">Kredensial dikirim ke nomor kamu</div>
          </div>
        </li>
      </ul>

      <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <i style="width:0%"></i>
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
@endsection

@push('scripts')
<script>
(function(){
  function copyTextById(id){
    var el = document.getElementById(id);
    if (!el) throw new Error('Target not found');
    var text = (el.textContent || '').trim();
    if (!text) throw new Error('Empty');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    // fallback (execCommand)
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
})();
</script>
@endpush

@push('scripts')
<script>
(function(){
  // ------ data dari server ------
  var ORDER_ID = @json($orderId);
  var CURRENT_STATUS = String(@json($status ?? '')).toUpperCase(); // normalisasi
  var HAS_CREDS = Boolean(@json((bool) $creds));

  // ------ dom refs (tanpa optional chaining) ------
  var LOADER = document.getElementById('smart-loader');
  var PROG = LOADER ? LOADER.querySelector('.progress > i') : null;
  var PROG_WR = PROG ? PROG.parentElement : null;
  var STEPS = LOADER ? LOADER.querySelectorAll('.step') : [];
  var ELAPSED = document.getElementById('elapsed');
  var BTN_REFRESH = document.getElementById('btn-refresh');

  // Tunjukkan loader jika PENDING atau PAID tapi belum ada kredensial
  function shouldShowLoader(){
    return (CURRENT_STATUS === 'PENDING') || (CURRENT_STATUS === 'PAID' && !HAS_CREDS);
  }

  // UI helpers
  function setStepState(activeIndex){ // 1..3
    if (!STEPS || !STEPS.length) return;
    for (var i=0;i<STEPS.length;i++){
      var li = STEPS[i];
      var pos = i+1;
      li.classList.remove('is-active'); li.classList.remove('is-done');
      if (pos < activeIndex) li.classList.add('is-done');
      else if (pos === activeIndex) li.classList.add('is-active');
    }
    var pct = Math.min(100, Math.max(0, (activeIndex-1) * 50)); // 0,50,100 untuk 3 step
    if (PROG){ PROG.style.width = pct + '%'; }
    if (PROG_WR){ PROG_WR.setAttribute('aria-valuenow', pct); }
  }

  var t0 = Date.now(), tickTmr = null, pollTmr = null;
  function startElapsed(){
    if (!ELAPSED) return;
    tickTmr = setInterval(function(){
      var s = Math.floor((Date.now()-t0)/1000);
      ELAPSED.textContent = s + 's';
    }, 1000);
  }

  // Poll status pembayaran → /payments/{orderId} (controller PaymentController@show)
  var interval = 2000, hardStopMs = 120000; // 2 menit
  function poll(){
    fetch('/payments/' + encodeURIComponent(ORDER_ID), { headers: { 'Accept':'application/json' }, credentials: 'same-origin' })
      .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
      .then(function(data){
        var status = String(data.status || '').toUpperCase();

        if (status === 'PENDING'){
          setStepState(1);
        } else if (status === 'PAID'){
          // Saat sudah PAID: anggap sedang provision/push → step 2
          setStepState(2);
          // Kalau halaman ini belum punya kredensial, reload ringan agar controller render creds
          if (!HAS_CREDS){
            setTimeout(function(){ location.reload(); }, 1200);
          } else {
            // Kalau sudah punya kredensial, finalisasi → step 3 & hide
            setStepState(3);
            hideLoader();
          }
        } else {
          // FAILED/CANCEL/EXPIRE/UNKNOWN → tutup loader (biar user lihat status di halaman)
          hideLoader();
        }
      })
      .catch(function(){
        // diamkan error jaringan sesaat
      })
      .finally(function(){
        if (!isVisible(LOADER)) return;
        if ((Date.now()-t0) > hardStopMs){ hideLoader(); return; }
        pollTmr = setTimeout(poll, interval);
      });
  }

  function isVisible(el){ return el && el.classList.contains('is-visible'); }
  function showLoader(){
    if (!LOADER) return;
    LOADER.classList.add('is-visible');
    setStepState(CURRENT_STATUS === 'PENDING' ? 1 : 2);
    startElapsed();
    poll();
  }
  function hideLoader(){
    if (!LOADER) return;
    LOADER.classList.remove('is-visible');
    if (pollTmr) clearTimeout(pollTmr);
    if (tickTmr) clearInterval(tickTmr);
  }

  if (ORDER_ID && shouldShowLoader()) showLoader();

  if (BTN_REFRESH){
    BTN_REFRESH.addEventListener('click', function(){ location.reload(); });
  }
})();
</script>
@endpush
