@extends('layouts.app')
@section('title','AdaPay — Hotspot Payments')

@section('content')
  <!-- Hero -->
  <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-sky-50 via-white to-sky-100 border">
    <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-sky-200/40 blur-3xl"></div>
    <div class="absolute -left-16 -bottom-16 h-64 w-64 rounded-full bg-cyan-200/40 blur-3xl"></div>

    <div class="relative p-6 md:p-10">
      <div class="flex items-center gap-3 mb-4">
        <img src="{{ asset('logo.png') }}" alt="AdaPay" class="h-10 w-auto rounded-md" onerror="this.style.display='none'">
        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">AdaPay</h1>
      </div>

      <div class="flex flex-wrap items-center gap-2 mb-3">
        <span class="inline-flex items-center gap-1.5 rounded-full border bg-white px-2.5 py-1 text-xs font-medium text-gray-700">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Instan & Tanpa Ribet
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full border bg-white px-2.5 py-1 text-xs font-medium text-gray-700">
          <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span> Auto-Provision MikroTik
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full border bg-white px-2.5 py-1 text-xs font-medium text-gray-700">
          <span class="h-1.5 w-1.5 rounded-full bg-fuchsia-500"></span> Webhook Real-time
        </span>
      </div>

      <p class="text-gray-700/90 max-w-2xl">
        Portal pembayaran <span class="font-semibold">voucher hotspot</span> yang simple dan cepat.
        Dukung <strong>QRIS</strong>, <strong>GoPay</strong>, <strong>ShopeePay</strong>, multi-client via subdomain,
        dan <em>provisioning</em> otomatis ke MikroTik. Bukti bayar diverifikasi real-time, user langsung aktif.
      </p>

      <div class="mt-6 flex flex-wrap items-center gap-3">
        <a href="{{ url('/hotspot') }}" class="btn btn--primary">
          <span class="btn__label">Beli Voucher</span>
          <span class="spinner hidden" aria-hidden="true"></span>
        </a>
        <a href="{{ url('/orders/track') }}" class="inline-flex items-center gap-2 rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a5 5 0 0 1 5 5c0 4.25-5 11-5 11S7 11.25 7 7a5 5 0 0 1 5-5Zm0 7.5A2.5 2.5 0 1 0 12 4a2.5 2.5 0 0 0 0 5.5ZM5 20.5a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Z"/></svg>
          Lacak Pesanan
        </a>
      </div>

      <!-- Quick stats -->
      <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 lg:max-w-3xl">
        <div class="rounded-xl border bg-white p-3">
          <div class="text-xs text-gray-500">Rata waktu aktivasi</div>
          <div class="mt-1 text-lg font-semibold">≈ 2–5 detik</div>
        </div>
        <div class="rounded-xl border bg-white p-3">
          <div class="text-xs text-gray-500">Tingkat sukses pembayaran</div>
          <div class="mt-1 text-lg font-semibold">99%*</div>
        </div>
        <div class="rounded-xl border bg-white p-3 col-span-2 md:col-span-1">
          <div class="text-xs text-gray-500">Uptime (rolling 30 hari)</div>
          <div class="mt-1 text-lg font-semibold">99.9%</div>
        </div>
      </div>
      <p class="mt-1 text-xs text-gray-500">*Tergantung ketersediaan kanal & bank/payment partner.</p>
    </div>
  </section>

  <!-- Keunggulan utama -->
  <section class="mt-8 grid gap-4 md:grid-cols-3">
    <div class="rounded-xl border bg-white p-4">
      <div class="flex items-center gap-2 font-semibold mb-1">
        <!-- icon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-sky-600" viewBox="0 0 24 24" fill="currentColor"><path d="M3 6a3 3 0 0 1 3-3h6a3 3 0 0 1 3 3v2H3V6Zm0 4h18v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-8Zm10 3a1 1 0 1 0-2 0v4a1 1 0 1 0 2 0v-4Z"/></svg>
        Kemudahan Pembayaran
      </div>
      <p class="text-sm text-gray-600">
        Satu kode <strong>QRIS</strong> untuk semua e-wallet & mobile banking, plus <strong>GoPay</strong> dan <strong>ShopeePay</strong>. Tidak perlu aplikasi tambahan.
      </p>
    </div>

    <div class="rounded-xl border bg-white p-4">
      <div class="flex items-center gap-2 font-semibold mb-1">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a4 4 0 0 1 4 4v3H8V5a4 4 0 0 1 4-4Zm6 7H6a2 2 0 0 0-2 2v9a4 4 0 0 0 4 4h8a4 4 0 0 0 4-4V10a2 2 0 0 0-2-2Z"/></svg>
        Multi-Client Otomatis
      </div>
      <p class="text-sm text-gray-600">
        Mapping <em>tenant</em> via subdomain, mis. <code class="px-1 bg-gray-100 rounded">c1.pay.example.com</code>.
        Branding & price list bisa berbeda per client.
      </p>
    </div>

    <div class="rounded-xl border bg-white p-4">
      <div class="flex items-center gap-2 font-semibold mb-1">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-fuchsia-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 1 0-6 6v3a9 9 0 1 1 0-18Z"/></svg>
        MikroTik Ready
      </div>
      <p class="text-sm text-gray-600">
        Auto-provision setelah status <em>PAID</em>. Bisa <strong>username+password</strong> atau <strong>voucher (kode = password)</strong>.
      </p>
    </div>
  </section>

  <!-- Cara kerja -->
  <section class="mt-8 rounded-xl border bg-white p-4">
    <h2 class="font-semibold mb-2">Cara Kerja Singkat</h2>
    <ol class="list-decimal ml-5 space-y-1.5 text-sm text-gray-700">
      <li>Pelanggan pilih paket/voucher & metode bayar.</li>
      <li>AdaPay membuat transaksi (QR/Deeplink) → pelanggan bayar.</li>
      <li>Webhook memvalidasi & menandai transaksi <strong>PAID</strong>.</li>
      <li>User hotspot dibuat & (opsional) langsung <em>push</em> ke MikroTik.</li>
      <li>Kredensial tampil otomatis di halaman pesanan & terkirim via WhatsApp/SMS*.</li>
    </ol>
    <p class="mt-2 text-xs text-gray-500">*Jika kanal notifikasi diaktifkan.</p>
  </section>

  <!-- Metode pembayaran -->
  <section class="mt-8 rounded-xl border bg-white p-4">
    <h2 class="font-semibold mb-3">Metode Pembayaran</h2>
    <div class="flex flex-wrap items-center gap-2 text-sm">
      <span class="px-2 py-1 rounded-md border bg-sky-50 text-sky-800">QRIS (semua bank/e-wallet)</span>
      <span class="px-2 py-1 rounded-md border bg-emerald-50 text-emerald-800">GoPay</span>
      <span class="px-2 py-1 rounded-md border bg-orange-50 text-orange-800">ShopeePay</span>
    </div>
    <p class="mt-2 text-xs text-gray-500">
      Dukungan otomatis untuk nominal tepat, status real-time, dan <em>expiry</em> pembayaran.
    </p>
  </section>

  <!-- Info cepat / keamanan / SLA -->
  <section class="mt-8 grid gap-4 md:grid-cols-3">
    <div class="rounded-xl border bg-white p-4">
      <div class="font-semibold mb-1">Monitoring & Status</div>
      <p class="text-sm text-gray-600">
        Sistem dipantau 24/7. Jika salah satu kanal pembayaran mengalami gangguan,
        aplikasi melakukan <em>auto-retry</em> dan menyarankan alternatif metode.
      </p>
      <a href="{{ url('/status') }}" class="mt-2 inline-flex text-xs text-sky-700 hover:text-sky-900 underline">Lihat status live</a>
    </div>

    <div class="rounded-xl border bg-white p-4">
      <div class="font-semibold mb-1">Keamanan</div>
      <ul class="text-sm text-gray-600 list-disc ml-5 space-y-1">
        <li>Signature webhook diverifikasi (HMAC SHA-512).</li>
        <li>Server Key disimpan di server, <strong>tidak</strong> pernah diekspos ke browser.</li>
        <li>Callback URL per-tenant & <em>allowlist</em> IP opsional.</li>
      </ul>
    </div>

    <div class="rounded-xl border bg-white p-4">
      <div class="font-semibold mb-1">SLA & Dukungan</div>
      <ul class="text-sm text-gray-600 list-disc ml-5 space-y-1">
        <li>Uptime target 99.9% (rolling 30 hari).</li>
        <li>Rata konfirmasi pembayaran &lt; 5 detik.</li>
        <li>Tim support via WhatsApp & email jam kerja.</li>
      </ul>
    </div>
  </section>

  <!-- Kenapa AdaPay -->
  <section class="mt-8 rounded-xl border bg-white p-4">
    <h2 class="font-semibold mb-2">Kenapa Memilih AdaPay?</h2>
    <div class="grid gap-4 md:grid-cols-2">
      <ul class="text-sm text-gray-700 list-disc ml-5 space-y-1">
        <li><strong>Setup cepat</strong>: cukup atur subdomain & kredensial pembayaran.</li>
        <li><strong>Tanpa training</strong>: UI sederhana, pelanggan tinggal scan & bayar.</li>
        <li><strong>Anti salah harga</strong>: nominal terkunci di <em>invoice</em>.</li>
        <li><strong>Auto-expire</strong>: transaksi kadaluarsa dibatalkan otomatis.</li>
      </ul>
      <ul class="text-sm text-gray-700 list-disc ml-5 space-y-1">
        <li><strong>Log lengkap</strong>: audit trail transaksi & webhook.</li>
        <li><strong>Scalable</strong>: multi-tenant, ribuan pesanan/hari.</li>
        <li><strong>Terintegrasi</strong>: MikroTik API, WhatsApp/SMS, dan laporan.</li>
        <li><strong>Ekonomis</strong>: biaya operasional efisien untuk UMKM sampai enterprise.</li>
      </ul>
    </div>
  </section>

  <!-- Integrasi Teknis singkat -->
  <section class="mt-8 rounded-xl border bg-white p-4">
    <h2 class="font-semibold mb-2">Integrasi Teknis</h2>
    <div class="grid gap-4 md:grid-cols-2">
      <div class="text-sm text-gray-700">
        <div class="font-medium mb-1">Webhook</div>
        <p class="text-gray-600">Kirim ke <code class="px-1 bg-gray-100 rounded">/webhook/payment/{client}</code> dengan header signature. Idempotent & aman.</p>
      </div>
      <div class="text-sm text-gray-700">
        <div class="font-medium mb-1">MikroTik</div>
        <p class="text-gray-600">Support <code class="px-1 bg-gray-100 rounded">/ip/hotspot/user</code> via API. Mode push langsung atau <em>pull</em> oleh agen.</p>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="mt-8 rounded-xl border bg-white p-4">
    <h2 class="font-semibold mb-3">FAQ</h2>
    <div class="space-y-2">
      <details class="group rounded-lg border p-3 [&_summary]:cursor-pointer">
        <summary class="font-medium text-sm text-gray-800">Butuh aplikasi khusus untuk bayar?</summary>
        <div class="mt-1.5 text-sm text-gray-600">Tidak. Pelanggan cukup scan <strong>QRIS</strong> dari mobile banking/e-wallet atau klik <em>deeplink</em> GoPay/ShopeePay.</div>
      </details>
      <details class="group rounded-lg border p-3 [&_summary]:cursor-pointer">
        <summary class="font-medium text-sm text-gray-800">Voucher aktif berapa lama setelah bayar?</summary>
        <div class="mt-1.5 text-sm text-gray-600">Biasanya &lt; 5 detik sejak pembayaran terkonfirmasi.</div>
      </details>
      <details class="group rounded-lg border p-3 [&_summary]:cursor-pointer">
        <summary class="font-medium text-sm text-gray-800">Bisa kirim user via WhatsApp?</summary>
        <div class="mt-1.5 text-sm text-gray-600">Bisa, jika integrasi notifikasi diaktifkan di pengaturan client.</div>
      </details>
    </div>
  </section>

  <section class="mt-10 rounded-2xl border bg-gradient-to-br from-white to-sky-50 p-6 md:p-8">
    <div class="grid gap-6 md:grid-cols-2">
      {{-- CTA untuk Client/Mitra --}}
      <div class="rounded-xl border bg-white p-6">
        <div class="inline-flex items-center gap-2 rounded-full border bg-sky-50 px-3 py-1 text-xs font-medium text-sky-800">
          Untuk Pemilik Hotspot / Mitra
        </div>
        <h3 class="mt-3 text-xl font-bold">Jualan voucher jadi secepat scan.</h3>
        <p class="mt-2 text-gray-600">
          Terima <strong>QRIS, GoPay, ShopeePay</strong>, status bayar realtime, dan <em>auto-provision</em> MikroTik.
          Multi-tenant via subdomain, laporan rapi, operasional efisien.
        </p>
        <ul class="mt-4 list-disc ml-5 text-sm text-gray-700 space-y-1">
          <li>Aktivasi user &lt; 5 detik setelah bayar</li>
          <li>Nominal terkunci & auto-expire invoice</li>
          <li>Audit trail & webhook terverifikasi</li>
        </ul>
        <div class="mt-5 flex flex-wrap gap-2">
          <a href="{{ url('/contact') }}" class="btn btn--primary">
            <span class="btn__label">Jadwalkan Demo</span>
          </a>
          <a href="https://wa.me/62859106992437?text=Halo%20AdaPay%2C%20saya%20ingin%20mendaftar%20sebagai%20mitra."
            target="_blank" rel="noopener"
            class="inline-flex items-center rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Chat Sales
          </a>
        </div>
      </div>

      {{-- CTA untuk Member/Pelanggan --}}
      <div class="rounded-xl border bg-white p-6">
        <div class="inline-flex items-center gap-2 rounded-full border bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800">
          Untuk Member / Pelanggan
        </div>
        <h3 class="mt-3 text-xl font-bold">Beli voucher? Tinggal scan.</h3>
        <p class="mt-2 text-gray-600">
          Bayar dengan <strong>QRIS & e-wallet</strong>, voucher aktif otomatis, kredensial tampil langsung di halaman pesanan.
        </p>
        <ul class="mt-4 list-disc ml-5 text-sm text-gray-700 space-y-1">
          <li>Pembayaran aman & cepat</li>
          <li>Status realtime—tanpa menunggu</li>
          <li>Harga transparan, tanpa biaya tersembunyi</li>
        </ul>
        <div class="mt-5 flex flex-wrap gap-2">
          <a href="{{ url('/hotspot') }}" class="btn btn--primary">
            <span class="btn__label">Beli Voucher</span>
          </a>
          <a href="{{ url('/orders/track') }}" class="inline-flex items-center rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Lacak Pesanan
          </a>
        </div>
      </div>
    </div>
  </section>
@endsection
