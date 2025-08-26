@extends('layouts.app')
@section('title', 'Status Pembayaran')

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

      // --- portal URL: controller ($hotspotPortal) -> model ($client->hotspot_portal) -> config fallback ---
      /** @var \App\Models\Client|null $client */
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
        <script>setTimeout(()=>location.reload(),1500)</script>
      @endif

    @elseif($status === 'PENDING')
      <div class="rounded border border-yellow-200 bg-yellow-50 p-3 mb-4">
        Menunggu pembayaran…
      </div>
      <script>setTimeout(()=>location.reload(),1500)</script>
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
