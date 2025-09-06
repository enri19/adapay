@extends('layouts.app')
@section('title', 'Beli Voucher Hotspot')

@push('head')
{{-- Midtrans Snap JS --}}
@php $isProd = config('midtrans.is_production', false); @endphp
<script src="{{ $isProd ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}"
        data-client-key="{{ config('midtrans.client_key') }}"></script>

<style>
  :root {
    --border-color-soft: #e5e7eb;
    --border-color-lighter: #eef2f7;
    --border-color-accent: #dbeafe;
    --bg-accent-soft: #f0f7ff;
    --brand-color: #2563eb;
    --brand-color-light: rgba(37,99,235,.12);
    --text-color-muted: #6b7280;
  }
  .panel { border: 1px solid var(--border-color-soft); border-radius: 1rem; background: #fff; padding: 1rem; }
  .panel--accent { background: linear-gradient(180deg, #f8fbff, #fff); }
  .panel-hd { display: flex; align-items: center; gap: .6rem; margin-bottom: .75rem; }
  .panel-ic { width: 20px; height: 20px; color: #0284c7; }
  .subcard { border: 1px solid var(--border-color-lighter); border-radius: .75rem; background: #fff; }
  .subcard-hd { padding: .75rem .9rem; border-bottom: 1px solid var(--border-color-lighter); font-weight: 600; }
  .subcard-bd { padding: .9rem; }
  .pay-section { margin-top: .5rem; }
  .pay-header { display: flex; align-items: baseline; gap: .5rem; margin-bottom: .4rem; }
  .pay-title { font-weight: 700; }
  .pay-desc { font-size: .85rem; color: var(--text-color-muted); }
  .pm-card {
    display: flex; align-items: center; justify-content: center; gap: .5rem;
    border: 1px solid var(--border-color-soft); border-radius: .6rem; background: #fff; padding: .6rem .75rem;
    cursor: pointer; user-select: none;
    transition: all .15s ease;
  }
  .pm-card:hover { background: #fafafa; }
  .pm-card:active { transform: translateY(1px); }
  .pm-card[aria-checked="true"] { border-color: var(--brand-color); box-shadow: 0 0 0 3px var(--brand-color-light); }
  .pm-radio { position: absolute; opacity: 0; pointer-events: none; width: 0; height: 0; }
  .summary { border: 1px solid var(--border-color-accent); background: var(--bg-accent-soft); border-radius: .75rem; padding: .75rem; }
  .summary-row { display: flex; justify-content: space-between; gap: .75rem; font-size: .92rem; }
  .summary-row + .summary-row { margin-top: .25rem; }
  .summary-total { font-weight: 700; }
  select:disabled { background: #f9fafb; color: var(--text-color-muted); cursor: not-allowed; }
  @media (max-width:768px){ .pay-methods{grid-template-columns:1fr 1fr} }
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
      // dari controller: $resolvedClientId bisa null (base host) atau string (subdomain/query)
      $selectedClientId  = (string) ($resolvedClientId ?? '');
      $selectedVoucherId = (string) old('voucher_id', '');
    @endphp

    <form id="formCheckout" class="space-y-3" novalidate>
      {{-- Tampil hanya jika di base host --}}
      @if ($isBaseHost)
        <div class="subcard">
          <div class="subcard-hd">Pilih Mitra</div>
          <div class="subcard-bd">
            <label for="clientSelect" class="block text-sm font-medium mb-1">Mitra</label>

            <select id="clientSelect" name="client"
                    class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200"
                    autocomplete="off" required>
              {{-- placeholder TIDAK disabled supaya bisa benar-benar terpilih --}}
              <option value="" {{ $selectedClientId === '' ? 'selected' : '' }}>— Pilih Mitra —</option>

              @forelse ($clients as $c)
                <option value="{{ $c->client_id }}"
                        data-slug="{{ $c->slug }}"
                        {{ (string)$selectedClientId === (string)$c->client_id ? 'selected' : '' }}>
                  {{ $c->name }} ({{ $c->client_id }})
                </option>
              @empty
                <option value="">Tidak ada mitra aktif</option>
              @endforelse
            </select>

            <small class="text-xs text-gray-500">Pilih mitra terlebih dahulu sebelum lanjut.</small>
          </div>
        </div>
      @endif

      {{-- Nilai client_id disimpan di sini sebagai satu-satunya sumber kebenaran --}}
      <input type="hidden" id="client_id" name="client_id" value="{{ $selectedClientId }}">

      {{-- Pilihan Voucher --}}
      <div class="subcard">
        <div class="subcard-hd">Pilih Voucher</div>
        <div class="subcard-bd">
          <label for="voucherSelect" class="block text-sm font-medium mb-1">Voucher</label>

          <select id="voucherSelect" name="voucher_id"
                  class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200"
                  {{ $selectedClientId === '' ? 'disabled' : '' }} required>
            {{-- SELALU ada placeholder agar tidak auto-select --}}
            <option value="" {{ $selectedVoucherId === '' ? 'selected' : '' }}>— Pilih Voucher —</option>

            @if($selectedClientId !== '' && $vouchers->isNotEmpty())
              @foreach($vouchers as $v)
                <option value="{{ $v->id }}"
                        data-name="{{ $v->name }}"
                        data-price="{{ (int)$v->price }}"
                        {{ (string)$selectedVoucherId === (string)$v->id ? 'selected' : '' }}>
                  {{ $v->name }} — Rp{{ number_format($v->price,0,',','.') }}
                </option>
              @endforeach
            @endif
          </select>

          <div id="noVoucherBox"
              class="mt-2 p-3 text-sm text-gray-600 border rounded bg-gray-50 {{ ($selectedClientId !== '' && $vouchers->isNotEmpty()) ? 'hidden' : '' }}">
            @if($selectedClientId === '')
              Pilih mitra untuk menampilkan voucher.
            @else
              Belum ada voucher untuk mitra ini.
            @endif
          </div>
        </div>
      </div>


      {{-- Data Pembeli --}}
      <div class="subcard">
        <div class="subcard-hd">Data Pembeli</div>
        <div class="subcard-bd">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <input name="name" id="fldName" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200" placeholder="Nama lengkap" required minlength="2" pattern=".*\S.*">
              <p id="errName" class="text-xs text-red-600 mt-1 hidden"></p>
            </div>
            <div>
              <input name="phone" id="fldPhone" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200" placeholder="No. WhatsApp (e.g., 0812...)" required minlength="9" pattern=".*\S.*">
              <p id="errPhone" class="text-xs text-red-600 mt-1 hidden"></p>
            </div>
          </div>
           <input name="email" id="fldEmail" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-200 mt-3" placeholder="Email (opsional)" type="email">
          <p class="mt-2 text-xs text-gray-500">
            Kredensial voucher akan tampil otomatis setelah pembayaran berhasil.
          </p>
        </div>
      </div>

      {{-- Metode Pembayaran (UI sederhana, Snap yang akan menampilkan pilihan) --}}
      {{-- <div class="subcard">
        <div class="subcard-hd">Metode Pembayaran</div>
        <div class="subcard-bd">
            <div class="pay-methods" role="radiogroup">
              <label class="pm-card" role="radio" aria-checked="true">
                <input class="pm-radio" type="radio" name="method" value="snap" checked>
                <span class="text-sm font-medium">Semua Metode (Snap Checkout)</span>
              </label>
            </div>
        </div>
      </div> --}}

      {{-- Ringkasan Pesanan --}}
      <div class="summary">
        <div class="summary-row">
          <span>Voucher</span>
          <span id="sumVoucherName">—</span>
        </div>
        <hr class="my-2 border-blue-100">
        <div class="summary-row summary-total">
          <span>Total</span>
          <span id="sumTotal">Rp0</span>
        </div>
      </div>

      <p id="payErr" class="text-xs text-red-600 hidden"></p>

      <button id="payBtn" type="submit" class="btn btn--primary"
        @if($selectedClientId === '' || $vouchers->isEmpty()) disabled @endif>
        <span class="btn__label">Lanjut ke Pembayaran</span>
        <span class="spinner hidden" aria-hidden="true"></span>
      </button>

      <div class="text-xs text-center text-gray-500">
        Dengan melanjutkan, Anda menyetujui <a href="{{ url('/agreement') }}" class="underline text-sky-700">Perjanjian Layanan</a> kami.
      </div>
    </form>
  </div>
</div>
@endsection

<script>
document.addEventListener('DOMContentLoaded', () => {
  "use strict";

  const API_VOUCHERS_URL = "{{ url('/api/hotspot/vouchers') }}";
  const API_SNAP_URL     = "{{ url('/api/hotspot/checkout-snap') }}";

  // DOM
  const formEl          = document.getElementById('formCheckout');
  const clientSelectEl  = document.getElementById('clientSelect');   // hanya ada di base host
  const clientIdHidden  = document.getElementById('client_id');      // sumber kebenaran
  const voucherSelectEl = document.getElementById('voucherSelect');
  const noVoucherBox    = document.getElementById('noVoucherBox');
  const payBtn          = document.getElementById('payBtn');
  const payErrBox       = document.getElementById('payErr');
  const nameInput       = document.getElementById('fldName');
  const phoneInput      = document.getElementById('fldPhone');
  const emailInput      = document.getElementById('fldEmail');
  const sumVoucherName  = document.getElementById('sumVoucherName');
  const sumTotal        = document.getElementById('sumTotal');

  // Helpers
  const rupiah = (n) => 'Rp' + Math.max(0, parseInt(n || 0, 10)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const normalizeClientId = (s) => String(s ?? '').trim();

  const setLoading = (btn, isLoading, text) => {
    if (!btn) return;
    const label = btn.querySelector('.btn__label');
    const spinner = btn.querySelector('.spinner');
    btn.disabled = isLoading;
    btn.setAttribute('aria-busy', isLoading);
    spinner?.classList.toggle('hidden', !isLoading);
    if (label) {
      if (btn.dataset.originalText === undefined) btn.dataset.originalText = label.textContent;
      label.textContent = (isLoading && text) ? text : btn.dataset.originalText;
    }
  };

  const showFieldError = (input, message) => {
    const errEl = document.getElementById(input.id.replace('fld', 'err'));
    if (!errEl) return;
    errEl.textContent = message || '';
    errEl.classList.toggle('hidden', !message);
    input.classList.toggle('border-red-500', !!message);
    input.setAttribute('aria-invalid', !!message);
  };

  const validateName = () => {
    const v = nameInput.value.trim();
    if (v.length < 2) return 'Nama wajib diisi (min. 2 karakter).';
    if (!/\S/.test(v)) return 'Nama tidak boleh hanya spasi.';
    return '';
  };

  const validatePhone = () => {
    const raw = (phoneInput.value || '').trim();
    if (!raw) return { msg: 'No. WhatsApp wajib diisi.', norm: '' };
    let norm = raw.replace(/\D+/g, '');
    if (norm.startsWith('0')) norm = '62' + norm.substring(1);
    else if (norm.startsWith('+62')) norm = norm.substring(1);
    if (!/^628[0-9]{8,13}$/.test(norm)) return { msg: 'Format No. WhatsApp tidak valid. Gunakan format 08...', norm: '' };
    return { msg: '', norm };
  };

  const updateSummary = () => {
    const opt = voucherSelectEl.options[voucherSelectEl.selectedIndex];
    if (!opt || !opt.value) {
      sumVoucherName.textContent = '—';
      sumTotal.textContent = rupiah(0);
      payBtn.disabled = true;
      return;
    }
    const name  = opt.dataset.name || 'Voucher';
    const price = parseInt(opt.dataset.price || 0, 10);
    sumVoucherName.textContent = name;
    sumTotal.textContent = rupiah(price);
    payBtn.disabled = false;
  };

  const renderVouchers = (vouchers = []) => {
    voucherSelectEl.innerHTML = '';

    // placeholder (tidak disabled supaya bisa tampil sebagai pilihan aktif)
    const ph = new Option('— Pilih Voucher —', '');
    ph.selected = true;
    voucherSelectEl.add(ph);

    if (!Array.isArray(vouchers) || vouchers.length === 0) {
      noVoucherBox?.classList.remove('hidden');
      voucherSelectEl.disabled = true;
      updateSummary();
      return;
    }

    vouchers.forEach(v => {
      const o = new Option(`${v.name} — ${rupiah(v.price)}`, v.id);
      o.dataset.name  = v.name;
      o.dataset.price = v.price;
      voucherSelectEl.add(o);
    });

    voucherSelectEl.disabled = false;
    noVoucherBox?.classList.add('hidden');
    updateSummary();
  };

  const fetchVouchers = async (clientId) => {
    if (!clientId) { renderVouchers([]); return; }
    voucherSelectEl.disabled = true;
    voucherSelectEl.innerHTML = '<option value="">Memuat voucher...</option>';
    payBtn.disabled = true;
    try {
      const res = await fetch(`${API_VOUCHERS_URL}?client_id=${encodeURIComponent(clientId)}`, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      renderVouchers(json.data || json.vouchers || json || []);
    } catch (e) {
      console.error('Gagal memuat voucher:', e);
      noVoucherBox.textContent = 'Gagal memuat voucher. Coba lagi nanti.';
      renderVouchers([]);
    }
  };

  const handleClientChange = () => {
    const newClientId = normalizeClientId(clientSelectEl.value);
    clientIdHidden.value = newClientId;

    const url = new URL(location.href);
    if (newClientId) url.searchParams.set('client', newClientId);
    else url.searchParams.delete('client');
    history.replaceState(null, '', url.toString());

    noVoucherBox.textContent = newClientId ? 'Memuat voucher...' : 'Pilih mitra untuk menampilkan voucher.';
    fetchVouchers(newClientId);
  };

  const startCheckout = async (e) => {
    e.preventDefault();
    payErrBox.classList.add('hidden');

    const nameErr = validateName();
    const phoneRes = validatePhone();
    showFieldError(nameInput, nameErr);
    showFieldError(phoneInput, phoneRes.msg);

    if (nameErr || phoneRes.msg || !voucherSelectEl.value) {
      if (!voucherSelectEl.value) { payErrBox.textContent = 'Silakan pilih voucher terlebih dahulu.'; payErrBox.classList.remove('hidden'); }
      return;
    }

    const opt = voucherSelectEl.options[voucherSelectEl.selectedIndex];
    const payload = {
      amount    : parseInt(opt.dataset.price || 0, 10),
      name      : nameInput.value.trim(),
      phone     : phoneRes.norm,
      email     : emailInput.value.trim() || null,
      voucher_id: Number(voucherSelectEl.value),
      client_id : clientIdHidden.value,
    };

    setLoading(payBtn, true, 'Memproses...');
    try {
      const res  = await fetch(API_SNAP_URL, { method:'POST', headers:{ 'Content-Type':'application/json', 'Accept':'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Gagal membuat transaksi.');
      if (!data.snap_token) throw new Error('Token pembayaran tidak valid.');

      window.snap.pay(data.snap_token, {
        onSuccess: () => location.href = `/hotspot/order/${encodeURIComponent(data.order_id)}`,
        onPending: () => location.href = `/hotspot/order/${encodeURIComponent(data.order_id)}`,
        onError  : () => location.href = `/hotspot/order/${encodeURIComponent(data.order_id)}?error=1`,
        onClose  : () => setLoading(payBtn, false),
      });
    } catch (err) {
      payErrBox.textContent = err.message;
      payErrBox.classList.remove('hidden');
      setLoading(payBtn, false);
    }
  };

  // === INIT (rapi & idempotent) ===
  const init = () => {
    // Events
    clientSelectEl && clientSelectEl.addEventListener('change', handleClientChange);
    voucherSelectEl && voucherSelectEl.addEventListener('change', updateSummary);
    nameInput && nameInput.addEventListener('blur', () => showFieldError(nameInput, validateName()));
    phoneInput && phoneInput.addEventListener('blur', () => showFieldError(phoneInput, validatePhone().msg));
    formEl && formEl.addEventListener('submit', startCheckout);

    // Subdomain flow: tidak ada dropdown client; pakai hidden client_id dari server
    if (!clientSelectEl) {
      const cid = normalizeClientId(clientIdHidden?.value);
      if (cid) {
        // Jika SSR belum isi opsi voucher, fetch; kalau sudah, cukup enable UI
        const onlyPlaceholder = voucherSelectEl.options.length <= 1 && (!voucherSelectEl.options.length || voucherSelectEl.options[0].value === '');
        if (onlyPlaceholder) {
          fetchVouchers(cid);
        } else {
          voucherSelectEl.disabled = false;
          noVoucherBox?.classList.add('hidden');
        }
      }
    }

    // Pastikan placeholder voucher terpilih bila belum ada pilihan valid
    if (voucherSelectEl && !voucherSelectEl.value) {
      voucherSelectEl.selectedIndex = 0;
    }

    updateSummary();
  };

  init();
});
</script>
