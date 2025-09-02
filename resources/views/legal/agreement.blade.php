@extends('layouts.app')
@section('title','Perjanjian Layanan — AdaPay')

@section('content')
@php
  $appName = config('app.name','AdaPay');
@endphp

<section class="rounded-2xl border bg-white p-6 md:p-8">
  <div class="max-w-4xl">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Perjanjian Layanan {{ $appName }}</h1>
        <p class="text-sm text-gray-600 mt-1">
          Versi {{ $version ?? '1.0' }} · Diperbarui {{ $lastUpdated ?? '-' }}
        </p>
      </div>
      <button onclick="window.print()" class="inline-flex items-center rounded-lg border bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
        Cetak
      </button>
    </div>

    {{-- Ringkasan singkat --}}
    <div class="mt-4 rounded-xl border bg-sky-50 p-4 text-sm text-gray-700">
      Dokumen ini mengatur syarat penggunaan {{ $appName }} untuk <strong>Client/Mitra</strong> (pemilik hotspot/penjual voucher) dan <strong>Member/Pelanggan</strong>.
      Dengan mengakses atau menggunakan layanan, Anda menyatakan telah membaca, memahami, dan menyetujui ketentuan di bawah ini.
      Jika tidak setuju, mohon hentikan penggunaan layanan.
    </div>

    {{-- Daftar isi --}}
    <nav class="mt-6 grid md:grid-cols-2 gap-2 text-sm">
      <a href="#definisi" class="underline text-sky-700">1. Definisi</a>
      <a href="#lingkup" class="underline text-sky-700">2. Ruang Lingkup Layanan</a>
      <a href="#kewajiban-client" class="underline text-sky-700">3. Kewajiban Client/Mitra</a>
      <a href="#kewajiban-adapay" class="underline text-sky-700">4. Kewajiban {{ $appName }}</a>
      <a href="#biaya" class="underline text-sky-700">5. Biaya & Pembayaran</a>
      <a href="#pembatasan" class="underline text-sky-700">6. Pembatasan Penggunaan</a>
      <a href="#keamanan" class="underline text-sky-700">7. Keamanan & Data</a>
      <a href="#sla" class="underline text-sky-700">8. SLA & Dukungan</a>
      <a href="#penghentian" class="underline text-sky-700">9. Suspensi & Penghentian</a>
      <a href="#tanggung-jawab" class="underline text-sky-700">10. Batasan Tanggung Jawab</a>
      <a href="#force-majeure" class="underline text-sky-700">11. Keadaan Kahar</a>
      <a href="#perubahan" class="underline text-sky-700">12. Perubahan Ketentuan</a>
      <a href="#hukum" class="underline text-sky-700">13. Hukum yang Berlaku</a>
      <a href="#kontak" class="underline text-sky-700">14. Kontak</a>
    </nav>

    {{-- 1. Definisi --}}
    <section id="definisi" class="mt-8">
      <h2 class="text-lg font-semibold mb-2">1. Definisi</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li><strong>{{ $appName }}</strong>: platform pembayaran voucher hotspot dengan integrasi penyedia pembayaran pihak ketiga dan otomatisasi MikroTik.</li>
        <li><strong>Client/Mitra</strong>: pemilik hotspot/penjual voucher yang menggunakan {{ $appName }} untuk memproses pembayaran dan mengaktifkan pengguna.</li>
        <li><strong>Member/Pelanggan</strong>: pembeli voucher hotspot yang melakukan pembayaran melalui kanal yang tersedia.</li>
        <li><strong>Penyedia Pembayaran</strong>: pihak ketiga yang memproses transaksi (mis. gateway pembayaran/e-wallet/bank).</li>
        <li><strong>Webhook</strong>: mekanisme notifikasi status pembayaran dari penyedia pembayaran ke {{ $appName }}.</li>
      </ul>
    </section>

    {{-- 2. Ruang Lingkup Layanan --}}
    <section id="lingkup" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">2. Ruang Lingkup Layanan</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Generasi tagihan (QR/tautan), penerimaan notifikasi pembayaran, dan aktivasi user hotspot secara otomatis.</li>
        <li>Dukungan <em>multi-tenant</em> melalui subdomain per client, konfigurasi harga, dan laporan dasar transaksi.</li>
        <li>{{ $appName }} bukan bank, dompet elektronik, atau lembaga keuangan; pemrosesan dana dilakukan oleh Penyedia Pembayaran.</li>
      </ul>
    </section>

    {{-- 3. Kewajiban Client/Mitra --}}
    <section id="kewajiban-client" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">3. Kewajiban Client/Mitra</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Memastikan materi/layanan yang dijual sah dan tidak melanggar hukum/ketentuan penyedia pembayaran.</li>
        <li>Menjaga kredensial (API key, Server Key) dan akses MikroTik dengan baik.</li>
        <li>Memberikan informasi harga, durasi, dan kebijakan refund/komplain yang jelas kepada pelanggan.</li>
        <li>Mematuhi peraturan perundang-undangan dan ketentuan penyedia pembayaran yang berlaku.</li>
      </ul>
    </section>

    {{-- 4. Kewajiban AdaPay --}}
    <section id="kewajiban-adapay" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">4. Kewajiban {{ $appName }}</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Menyediakan sistem yang wajar andal untuk pembuatan tagihan, penerimaan notifikasi (webhook), dan aktivasi user.</li>
        <li>Menjaga keamanan data yang diproses sesuai praktik yang layak dan kebijakan privasi yang berlaku.</li>
        <li>Memberikan dukungan teknis pada jam kerja sebagaimana tercantum pada halaman <em>Hubungi Kami</em>.</li>
      </ul>
    </section>

    {{-- 5. Biaya & Pembayaran --}}
    <section id="biaya" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">5. Biaya & Pembayaran</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Struktur biaya (mis. biaya platform/administrasi) akan diinformasikan dan dapat berubah dengan pemberitahuan sebelumnya.</li>
        <li>Biaya pihak ketiga dari Penyedia Pembayaran mengikuti ketentuan masing-masing penyedia.</li>
        <li>Settlement dana kepada Client dilakukan oleh Penyedia Pembayaran sesuai pengaturan akun merchant terkait.</li>
      </ul>
    </section>

    {{-- 6. Pembatasan Penggunaan --}}
    <section id="pembatasan" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">6. Pembatasan Penggunaan</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Dilarang menggunakan {{ $appName }} untuk aktivitas ilegal, penipuan, atau konten yang melanggar kebijakan penyedia pembayaran/hukum.</li>
        <li>Dilarang melakukan <em>reverse engineering</em>, penyalahgunaan API, atau beban trafik yang tidak wajar.</li>
      </ul>
    </section>

    {{-- 7. Keamanan & Data --}}
    <section id="keamanan" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">7. Keamanan & Data</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Webhook menggunakan verifikasi tanda tangan (mis. HMAC) dan Server Key tidak diekspos ke browser.</li>
        <li>Data pribadi diproses sesuai kebutuhan operasional layanan. Detail lebih lanjut tersedia pada Kebijakan Privasi.</li>
        <li>Client wajib mengamankan perangkat & kredensial yang terhubung (termasuk API MikroTik).</li>
      </ul>
    </section>

    {{-- 8. SLA & Dukungan --}}
    <section id="sla" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">8. SLA & Dukungan</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Target uptime layanan 99.9% (rolling 30 hari) dan rata konfirmasi pembayaran &lt; 5 detik.</li>
        <li>Dukungan teknis tersedia pada jam kerja melalui kanal yang diumumkan.</li>
      </ul>
    </section>

    {{-- 9. Suspensi & Penghentian --}}
    <section id="penghentian" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">9. Suspensi & Penghentian</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>{{ $appName }} dapat melakukan suspensi/penghentian jika ditemukan pelanggaran ketentuan atau indikasi penyalahgunaan.</li>
        <li>Client dapat menghentikan penggunaan kapan saja; kewajiban yang telah timbul tetap harus dipenuhi.</li>
      </ul>
    </section>

    {{-- 10. Batasan Tanggung Jawab --}}
    <section id="tanggung-jawab" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">10. Batasan Tanggung Jawab</h2>
      <p class="text-sm text-gray-700">
        {{ $appName }} tidak bertanggung jawab atas kerugian tidak langsung, insidental, atau konsekuensial; dan tidak menjamin
        ketersediaan kanal pembayaran pihak ketiga setiap saat. Dalam keadaan apa pun, total tanggung jawab {{ $appName }} terbatas
        pada nilai biaya platform yang dibayarkan Client untuk periode 1 (satu) bulan terakhir sebelum klaim.
      </p>
    </section>

    {{-- 11. Force Majeure --}}
    <section id="force-majeure" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">11. Keadaan Kahar (Force Majeure)</h2>
      <p class="text-sm text-gray-700">
        Para pihak dibebaskan dari kewajiban yang terhalang oleh kejadian di luar kendali wajar seperti bencana alam,
        gangguan jaringan luas, peperangan, kebijakan pemerintah, dan kejadian serupa.
      </p>
    </section>

    {{-- 12. Perubahan Ketentuan --}}
    <section id="perubahan" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">12. Perubahan Ketentuan</h2>
      <p class="text-sm text-gray-700">
        {{ $appName }} dapat memperbarui perjanjian ini dari waktu ke waktu. Versi terbaru menggantikan versi sebelumnya
        dan berlaku setelah diumumkan pada halaman ini.
      </p>
    </section>

    {{-- 13. Hukum yang Berlaku --}}
    <section id="hukum" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">13. Hukum yang Berlaku & Penyelesaian Sengketa</h2>
      <p class="text-sm text-gray-700">
        Perjanjian ini diatur oleh hukum Republik Indonesia. Sengketa yang timbul akan diselesaikan terlebih dahulu secara musyawarah,
        dan jika diperlukan melalui pengadilan negeri yang berwenang di domisili operasional {{ $appName }}.
      </p>
    </section>

    {{-- 14. Kontak --}}
    <section id="kontak" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">14. Kontak</h2>
      <div class="grid md:grid-cols-3 gap-4 text-sm">
        <div class="rounded-xl border bg-white p-4">
          <div class="text-xs text-gray-500">Email Support</div>
          <div class="font-semibold">support@adanih.info</div>
        </div>
        <div class="rounded-xl border bg-white p-4">
          <div class="text-xs text-gray-500">Jam Layanan</div>
          <div class="font-semibold">Sen–Jum, 09.00–17.00 WIB</div>
        </div>
        <div class="rounded-xl border bg-white p-4">
          <div class="text-xs text-gray-500">Kebijakan Privasi</div>
          <a href="{{ url('/privacy') }}" class="font-semibold text-sky-700 underline">Baca di sini</a>
        </div>
      </div>
    </section>

    <div class="mt-8 rounded-xl border bg-slate-50 p-4 text-xs text-slate-700">
      <strong>Penafian:</strong> Teks ini disediakan sebagai contoh perjanjian layanan dan tidak merupakan nasihat hukum.
      Silakan sesuaikan dengan kebutuhan bisnis Anda atau konsultasikan dengan penasihat hukum.
    </div>
  </div>
</section>
@endsection
