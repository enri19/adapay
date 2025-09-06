@extends('layouts.app')
@section('title', 'Status Pesanan')

@push('head')
<style>
  :root {
    --border-color-soft: #e5e7eb;
    --border-color-lighter: #eef2f7;
    --border-color-accent: #dbeafe;
    --bg-accent-soft: #f0f7ff;
    --text-color-muted: #6b7280;
  }
  .panel { border: 1px solid var(--border-color-soft); border-radius: 1rem; background: #fff; padding: 1rem; }
  .panel--accent { background: linear-gradient(180deg, #f8fbff, #fff); }
  .panel-hd { display: flex; align-items: center; gap: .6rem; margin-bottom: .75rem; }
  .panel-ic { width: 24px; height: 24px; color: #0284c7; }
  .subcard { border: 1px solid var(--border-color-lighter); border-radius: .75rem; background: #fff; }
  .subcard-hd { padding: .75rem .9rem; border-bottom: 1px solid var(--border-color-lighter); font-weight: 600; }
  .subcard-bd { padding: .9rem; }
  .summary { border: 1px solid var(--border-color-accent); background: var(--bg-accent-soft); border-radius: .75rem; padding: .75rem; }
  .summary-row { display: flex; justify-content: space-between; gap: .75rem; font-size: .92rem; }
  .muted { color: var(--text-color-muted); }
  .hidden { display: none; }
  .status-badge { font-weight: 600; padding: .2rem .5rem; border-radius: .5rem; font-size: .8rem; display: inline-block; }
  .status-badge--pending { background-color: #fffbeb; color: #b45309; }
  .status-badge--success { background-color: #f0fdf4; color: #15803d; }
  .status-badge--failed { background-color: #fef2f2; color: #b91c1c; }
</style>
@endpush

@section('content')
<div class="max-w-xl mx-auto p-1">
  <div class="panel panel--accent">
    <div class="panel-hd">
      <svg class="panel-ic" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z" clip-rule="evenodd" /></svg>
      <div>
        <h1 class="text-xl font-semibold leading-tight">Status Pesanan Anda</h1>
        <p class="text-sm muted">Berikut adalah detail dan status pesanan Anda.</p>
      </div>
    </div>

    {{-- Status Pembayaran --}}
    <div class="subcard mb-3">
      <div class="subcard-hd">Status Pembayaran</div>
      <div class="subcard-bd">
        <div id="statusContainer" class="text-sm text-gray-700">Memuat status...</div>
      </div>
    </div>

    {{-- Kredensial Hotspot (muncul setelah lunas) --}}
    <div id="credentialsBox" class="subcard mb-3 hidden">
      <div class="subcard-hd">Akun Hotspot Anda</div>
      <div class="subcard-bd space-y-2">
        <div>
            <label class="text-xs muted">Username</label>
            <div class="font-mono bg-gray-100 p-2 rounded text-gray-800">
                <code id="credentialUser"></code>
                <button type="button" class="copy-btn js-copy" data-copy="credentialUser" aria-label="Salin kode">
                  <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                  </svg>
                  <svg class="ic ic-ok hidden" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6L9 17l-5-5"></path>
                  </svg>
                </button>
            </div>
        </div>
        <div>
            <label class="text-xs muted">Password</label>
            <div class="font-mono bg-gray-100 p-2 rounded text-gray-800">
                <code id="credentialPass"></code>
                <button type="button" class="copy-btn js-copy" data-copy="credentialPass" aria-label="Salin kode">
                  <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                  </svg>
                  <svg class="ic ic-ok hidden" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6L9 17l-5-5"></path>
                  </svg>
                </button>
            </div>
        </div>
        <p id="credentialHint" class="text-xs text-gray-600 pt-1"></p>
      </div>
    </div>

    {{-- Detail Order ID --}}
    <div class="summary mt-3">
      <div class="summary-row">
        <span class="muted">Order ID</span>
        <span class="font-mono">{{ $orderId }}</span>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  "use strict";

  // ===== 1. KONFIGURASI & VARIABEL =====
  const ORDER_ID = @json($orderId);
  const POLLING_INTERVAL_MS = 5000; // Cek status setiap 5 detik
  const API_BASE_URL = "{{ url('/api') }}";

  // ===== 2. ELEMEN DOM =====
  const statusContainer = document.getElementById('statusContainer');
  const credentialsBox = document.getElementById('credentialsBox');
  const credUser = document.getElementById('credentialUser');
  const credPass = document.getElementById('credentialPass');
  const credHint = document.getElementById('credentialHint');

  // Tombol copy
  const btnCopyUser = document.querySelector('.js-copy[data-copy="credentialUser"]');
  const btnCopyPass = document.querySelector('.js-copy[data-copy="credentialPass"]');
  
  // ===== 3. STATUS APLIKASI =====
  let pollingId = null;
  let isPaymentFinished = false;

  // ===== 4. FUNGSI HELPER =====
  const show = (el) => el?.classList.remove('hidden');
  
  const setStatusBadge = (statusText, type = 'pending') => {
      const typeMap = {
          pending: 'status-badge--pending',
          success: 'status-badge--success',
          failed: 'status-badge--failed'
      };
      statusContainer.innerHTML = `<span class="status-badge ${typeMap[type]}">${statusText}</span>`;
  };

  // ===== 5. LOGIKA UTAMA =====
  
  /**
   * Mengambil kredensial hotspot dari API dan menampilkannya.
   */
  const fetchAndShowCredentials = async () => {
    try {
      const response = await fetch(`${API_BASE_URL}/hotspot/credentials/${ORDER_ID}`, { headers: {'Accept': 'application/json'} });
      if (!response.ok) return;
      const cred = await response.json();
      
      if (cred?.ready) {
        show(credentialsBox);
        
        // Isi nilai
        credUser.textContent = cred.username;

        const isCode = (cred.mode === 'code');
        credPass.textContent = isCode ? '(sama dengan username)' : (cred.password ?? '');

        // Update hint
        credHint.textContent = isCode
          ? 'Gunakan kode di atas untuk Username DAN Password pada halaman login Wi-Fi.'
          : 'Gunakan Username dan Password di atas pada halaman login Wi-Fi.';

        // Hapus tombol copy di Password jika mode 'code'
        if (btnCopyPass) {
          if (isCode) {
            btnCopyPass.remove(); // benar-benar dihapus dari DOM
          } else {
            // pastikan tampil & aktif jika bukan mode 'code'
            btnCopyPass.classList.remove('hidden');
            btnCopyPass.removeAttribute('disabled');
          }
        }
      }
    } catch (error) {
      console.error('Gagal mengambil kredensial:', error);
    }
  };

  /**
   * Fungsi utama untuk memeriksa status pembayaran secara periodik.
   */
  const checkPaymentStatus = async () => {
    if (isPaymentFinished) {
      if (pollingId) clearInterval(pollingId);
      return;
    }

    try {
      const response = await fetch(`${API_BASE_URL}/payments/${ORDER_ID}`, { headers: {'Accept': 'application/json'} });
      if (!response.ok) {
        setStatusBadge('Gagal memuat', 'failed');
        return;
      }
      const data = await response.json();
      
      const status = (data?.transaction_status ?? data?.status ?? 'pending').toLowerCase();
      const isPaid = ['settlement', 'capture', 'paid', 'success'].includes(status);

      if (isPaid) {
        isPaymentFinished = true;
        setStatusBadge('Pembayaran Berhasil', 'success');
        await fetchAndShowCredentials();
        if (pollingId) clearInterval(pollingId);
      } else if (['expire', 'cancel', 'deny', 'failure'].includes(status)) {
          isPaymentFinished = true;
          setStatusBadge(`Pembayaran ${status}`, 'failed');
          if (pollingId) clearInterval(pollingId);
      } else {
        setStatusBadge('Menunggu Pembayaran', 'pending');
      }
    } catch (error) {
      console.error('Polling error:', error);
      setStatusBadge('Koneksi Gagal', 'failed');
    }
  };

  // ===== 6. INISIALISASI =====
  const init = () => {
    checkPaymentStatus(); // Panggil sekali saat halaman dimuat
    pollingId = setInterval(checkPaymentStatus, POLLING_INTERVAL_MS);
  };

  init();
});
</script>

<!-- --- Copy helpers (konsisten) --- -->
<script>
  // Ambil teks dari elemen (mendukung <input>/<textarea> dan elemen biasa)
  const getTextFromEl = (el) => {
    if (!el) return '';
    return (el.value ?? el.textContent ?? '').toString().trim();
  };

  const copyTextById = async (id) => {
    const el = document.getElementById(id);
    if (!el) throw new Error('Target not found');
    const text = getTextFromEl(el);
    if (!text) throw new Error('Empty');

    // Prefer API clipboard modern
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
      return;
    }

    // Fallback aman (tanpa scroll-jump)
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
  };

  // Delegasi klik untuk tombol .js-copy
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-copy');
    if (!btn) return;

    const target = btn.dataset.copy || btn.getAttribute('data-copy');
    if (!target) return;

    btn.setAttribute('disabled', 'disabled');

    copyTextById(target)
      .then(() => {
        const ok = btn.querySelector('.ic-ok');
        const ic = btn.querySelector('.ic:not(.ic-ok)');
        if (ok && ic) {
          ic.classList.add('hidden');
          ok.classList.remove('hidden');
        }
        setTimeout(() => {
          if (ok && ic) {
            ok.classList.add('hidden');
            ic.classList.remove('hidden');
          }
          btn.removeAttribute('disabled');
        }, 1000);
      })
      .catch((err) => {
        console.error('Copy failed:', err);
        btn.removeAttribute('disabled');
        alert('Gagal menyalin.');
      });
  });
</script>
@endpush
