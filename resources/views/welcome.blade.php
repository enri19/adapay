@extends('layouts.app')
@section('title','AdaPay — Hotspot Payments')

@section('content')
  <!-- Hero -->
  <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-sky-50 via-white to-sky-100 border">
    <div class="p-6 md:p-10">
      <div class="flex items-center gap-3 mb-4">
        <img src="{{ asset('logo.png') }}" alt="AdaPay" class="h-10 w-auto rounded-md" onerror="this.style.display='none'">
        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">AdaPay</h1>
      </div>
      <p class="text-gray-600 max-w-2xl">
        Portal pembayaran untuk voucher hotspot. Dukungan <strong>QRIS</strong>, <strong>GoPay</strong>, dan <strong>ShopeePay</strong>,
        webhook real-time, multi-client via subdomain, serta provisioning otomatis ke MikroTik.
      </p>

      <div class="mt-6 flex flex-wrap items-center gap-3">
        <a href="{{ url('/hotspot') }}" class="btn btn--primary">
          <span class="btn__label">Beli Voucher</span>
          <span class="spinner hidden" aria-hidden="true"></span>
        </a>
      </div>
    </div>
  </section>

  <!-- Fitur ringkas -->
  <section class="mt-8 grid gap-4 md:grid-cols-3">
    <div class="rounded-xl border bg-white p-4">
      <div class="font-semibold mb-1">Pembayaran</div>
      <p class="text-sm text-gray-600">
        QRIS (Dana/OVO/Shopee/LinkAja via scan), GoPay & ShopeePay e-wallet.
      </p>
    </div>
    <div class="rounded-xl border bg-white p-4">
      <div class="font-semibold mb-1">Multi-Client</div>
      <p class="text-sm text-gray-600">
        Mapping otomatis berdasarkan subdomain, mis. <code class="px-1 bg-gray-100 rounded">c1.pay.example.com</code>.
      </p>
    </div>
    <div class="rounded-xl border bg-white p-4">
      <div class="font-semibold mb-1">MikroTik</div>
      <p class="text-sm text-gray-600">
        Auto-provision user hotspot setelah pembayaran <em>PAID</em>. Dukungan username+password atau kode voucher (sama).
      </p>
    </div>
  </section>

  <!-- Cara kerja -->
  <section class="mt-8 rounded-xl border bg-white p-4">
    <h2 class="font-semibold mb-2">Cara kerja singkat</h2>
    <ol class="list-decimal ml-5 space-y-1 text-sm text-gray-700">
      <li>Pelanggan pilih voucher & metode bayar di halaman Hotspot.</li>
      <li>Membuat transaksi (QR/Deeplink). Pelanggan bayar.</li>
      <li>Webhook menandai transaksi <strong>PAID</strong>.</li>
      <li>AdaPay membuat user hotspot & (opsional) push ke MikroTik.</li>
      <li>Kredensial tampil otomatis di halaman order.</li>
    </ol>
  </section>

  <!-- Metode pembayaran -->
  <section class="mt-8 rounded-xl border bg-white p-4">
    <h2 class="font-semibold mb-3">Metode pembayaran</h2>
    <div class="flex flex-wrap items-center gap-2 text-sm">
      <span class="px-2 py-1 rounded-md border bg-sky-50 text-sky-800">QRIS</span>
      <span class="px-2 py-1 rounded-md border bg-emerald-50 text-emerald-800">GoPay</span>
      <span class="px-2 py-1 rounded-md border bg-orange-50 text-orange-800">ShopeePay</span>
    </div>
  </section>

  <!-- Info cepat -->
  <section class="mt-8 grid gap-4 md:grid-cols-2">
    <div class="rounded-xl border bg-white p-4">
      <div class="font-semibold mb-1">Status sistem</div>
      <p class="text-sm text-gray-600">
        Sandbox kadang mengembalikan <code class="px-1 bg-gray-100 rounded">500</code>. Aplikasi akan <em>retry</em> dan tetap menampilkan link/QR bila tersedia.
      </p>
    </div>
    <div class="rounded-xl border bg-white p-4">
      <div class="font-semibold mb-1">Keamanan</div>
      <p class="text-sm text-gray-600">
        Signature webhook diverifikasi; Server Key tidak pernah diekspos ke browser.
      </p>
    </div>
  </section>
@endsection
