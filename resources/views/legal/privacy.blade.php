@extends('layouts.app')
@section('title','Kebijakan Privasi — AdaPay')

@section('content')
@php
  $appName = config('app.name','AdaPay');
@endphp

<section class="rounded-2xl border bg-white p-6 md:p-8">
  <div class="max-w-4xl">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Kebijakan Privasi {{ $appName }}</h1>
        <p class="text-sm text-gray-600 mt-1">Versi {{ $version ?? '1.0' }} · Diperbarui {{ $lastUpdated ?? '-' }}</p>
      </div>
      <button onclick="window.print()" class="inline-flex items-center rounded-lg border bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
        Cetak
      </button>
    </div>

    <div class="mt-4 rounded-xl border bg-sky-50 p-4 text-sm text-gray-700">
      Dokumen ini menjelaskan bagaimana {{ $appName }} mengumpulkan, menggunakan, menyimpan, dan membagikan data pribadi
      terkait pemrosesan pembayaran voucher hotspot dan aktivasi pengguna. Dengan menggunakan layanan, Anda menyetujui praktik
      privasi yang diuraikan di bawah ini.
    </div>

    {{-- Daftar Isi --}}
    <nav class="mt-6 grid md:grid-cols-2 gap-2 text-sm">
      <a href="#data-dikumpulkan" class="underline text-sky-700">1. Data yang Kami Kumpulkan</a>
      <a href="#cara-penggunaan" class="underline text-sky-700">2. Cara Kami Menggunakan Data</a>
      <a href="#dasar-hukum" class="underline text-sky-700">3. Dasar Hukum Pemrosesan</a>
      <a href="#berbagi" class="underline text-sky-700">4. Berbagi Data ke Pihak Ketiga</a>
      <a href="#cookies" class="underline text-sky-700">5. Cookie & Teknologi Serupa</a>
      <a href="#retensi" class="underline text-sky-700">6. Retensi Data</a>
      <a href="#keamanan" class="underline text-sky-700">7. Keamanan</a>
      <a href="#transfer" class="underline text-sky-700">8. Lokasi Pemrosesan & Transfer Data</a>
      <a href="#hak" class="underline text-sky-700">9. Hak Anda</a>
      <a href="#anak" class="underline text-sky-700">10. Anak di Bawah Umur</a>
      <a href="#tautan" class="underline text-sky-700">11. Tautan Pihak Ketiga</a>
      <a href="#perubahan" class="underline text-sky-700">12. Perubahan Kebijakan</a>
      <a href="#kontak" class="underline text-sky-700">13. Kontak</a>
    </nav>

    {{-- 1. Data yang Kami Kumpulkan --}}
    <section id="data-dikumpulkan" class="mt-8">
      <h2 class="text-lg font-semibold mb-2">1. Data yang Kami Kumpulkan</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li><strong>Data transaksi</strong>: order_id, status, jumlah, metode pembayaran, referensi penyedia, waktu pembuatan/pembayaran/kedaluwarsa.</li>
        <li><strong>Data pesanan hotspot</strong>: nama pembeli, email, nomor WhatsApp/telepon, paket/voucher yang dipilih.</li>
        <li><strong>Data teknis</strong>: alamat IP, user agent, tipe perangkat/OS, log kesalahan, cap waktu (timestamp).</li>
        <li><strong>Data komunikasi</strong>: pesan yang Anda kirim melalui formulir kontak, email support, atau kanal WhatsApp/SMS (jika diaktifkan).</li>
        <li><strong>Webhook & log gateway</strong>: payload notifikasi dari penyedia pembayaran untuk verifikasi status.</li>
        <li><strong>Cookie/analytics</strong> (jika digunakan): preferensi sesi, performa halaman, dan metrik agregat non-identifikasi.</li>
      </ul>
    </section>

    {{-- 2. Cara Kami Menggunakan Data --}}
    <section id="cara-penggunaan" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">2. Cara Kami Menggunakan Data</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Menyediakan layanan: membuat tagihan (QR/tautan), menerima notifikasi pembayaran, dan mengaktifkan user hotspot.</li>
        <li>Komunikasi transaksional: mengirim status pesanan, kredensial, dan bantuan terkait dukungan.</li>
        <li>Keamanan & pencegahan penipuan: verifikasi signature webhook, audit trail, dan monitoring anomali.</li>
        <li>Peningkatan layanan: analisis performa, troubleshooting, dan pengembangan fitur.</li>
        <li>Kepatuhan hukum: pemenuhan kewajiban hukum dan permintaan sah dari otoritas.</li>
      </ul>
    </section>

    {{-- 3. Dasar Hukum --}}
    <section id="dasar-hukum" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">3. Dasar Hukum Pemrosesan</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li><strong>Pelaksanaan kontrak</strong>: untuk memproses pesanan dan menyediakan layanan inti.</li>
        <li><strong>Kepentingan sah</strong>: menjaga keamanan, mencegah penyalahgunaan, dan meningkatkan layanan.</li>
        <li><strong>Kepatuhan hukum</strong>: memenuhi kewajiban peraturan yang berlaku (mis. perpajakan, audit).</li>
        <li><strong>Persetujuan</strong>: untuk aktivitas yang memerlukannya (mis. notifikasi tertentu/marketing—jika diaktifkan).</li>
      </ul>
    </section>

    {{-- 4. Berbagi Data --}}
    <section id="berbagi" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">4. Berbagi Data ke Pihak Ketiga</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li><strong>Penyedia pembayaran</strong> (mis. gateway/e-wallet/bank) untuk memproses transaksi.</li>
        <li><strong>Penyedia pesan</strong> (email/WhatsApp/SMS) untuk pengiriman notifikasi yang Anda minta/izinkan.</li>
        <li><strong>Hosting & infrastruktur</strong> untuk menjalankan aplikasi, penyimpanan log, dan backup.</li>
        <li><strong>Otoritas</strong> ketika diwajibkan oleh hukum atau perintah yang sah.</li>
        <li><strong>Peralihan bisnis</strong> (merger/akuisisi) dengan perlindungan yang setara.</li>
      </ul>
      <p class="text-sm text-gray-600 mt-2">Kami tidak menjual data pribadi Anda.</p>
    </section>

    {{-- 5. Cookies --}}
    <section id="cookies" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">5. Cookie & Teknologi Serupa</h2>
      <p class="text-sm text-gray-700">
        Kami dapat menggunakan cookie esensial untuk sesi dan keamanan, serta cookie non-esensial/analytics (jika diaktifkan) untuk performa.
        Anda dapat mengatur preferensi cookie melalui pengaturan browser. Memblokir cookie tertentu dapat memengaruhi fungsi situs.
      </p>
    </section>

    {{-- 6. Retensi --}}
    <section id="retensi" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">6. Retensi Data</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Data transaksi & pesanan disimpan sesuai kebutuhan operasional dan kewajiban hukum.</li>
        <li>Log aplikasi & webhook biasanya disimpan hingga <strong>90 hari</strong> untuk keperluan keamanan & audit.</li>
        <li>Data dukungan (ticket/email) disimpan hingga <strong>12 bulan</strong> sejak kasus ditutup.</li>
      </ul>
      <p class="text-xs text-gray-500 mt-1">Catatan: periode retensi dapat berbeda tergantung ketentuan regulator/partner.</p>
    </section>

    {{-- 7. Keamanan --}}
    <section id="keamanan" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">7. Keamanan</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Transport terenkripsi (TLS) dan verifikasi signature pada webhook.</li>
        <li>Pemisahan kredensial sensitif (Server Key tidak diekspos ke browser).</li>
        <li>Kontrol akses berbasis peran & prinsip minim hak akses.</li>
      </ul>
      <p class="text-xs text-gray-500 mt-1">Tidak ada metode transmisi/penyimpanan elektronik yang 100% aman; kami berupaya wajar untuk melindungi data Anda.</p>
    </section>

    {{-- 8. Transfer Data --}}
    <section id="transfer" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">8. Lokasi Pemrosesan & Transfer Data</h2>
      <p class="text-sm text-gray-700">
        Data dapat diproses di dalam atau di luar Indonesia oleh penyedia layanan kami. Kami memastikan adanya perlindungan yang memadai
        melalui perjanjian dan standar keamanan yang layak sesuai hukum yang berlaku.
      </p>
    </section>

    {{-- 9. Hak Anda --}}
    <section id="hak" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">9. Hak Anda</h2>
      <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
        <li>Akses, koreksi, atau pembaruan data pribadi Anda.</li>
        <li>Meminta penghapusan atau pembatasan pemrosesan sebagaimana diizinkan hukum.</li>
        <li>Menolak pemrosesan tertentu dan/atau menarik persetujuan (jika dasar pemrosesan adalah persetujuan).</li>
        <li>Mendapatkan salinan data (portabilitas) ketika berlaku.</li>
      </ul>
      <p class="text-sm text-gray-700 mt-2">
        Untuk menggunakan hak-hak ini, hubungi kami melalui email pada bagian <a href="#kontak" class="underline text-sky-700">Kontak</a>.
        Kami dapat meminta verifikasi identitas untuk keamanan.
      </p>
    </section>

    {{-- 10. Anak --}}
    <section id="anak" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">10. Anak di Bawah Umur</h2>
      <p class="text-sm text-gray-700">
        Layanan tidak ditujukan untuk individu berusia di bawah 18 tahun. Jika Anda yakin kami menyimpan data anak tanpa persetujuan yang sah,
        hubungi kami untuk penghapusan.
      </p>
    </section>

    {{-- 11. Tautan Pihak Ketiga --}}
    <section id="tautan" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">11. Tautan Pihak Ketiga</h2>
      <p class="text-sm text-gray-700">
        Situs kami dapat berisi tautan ke layanan pihak ketiga. Kami tidak bertanggung jawab atas praktik privasi mereka.
        Kami mendorong Anda untuk meninjau kebijakan privasi masing-masing pihak ketiga.
      </p>
    </section>

    {{-- 12. Perubahan --}}
    <section id="perubahan" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">12. Perubahan Kebijakan</h2>
      <p class="text-sm text-gray-700">
        Kami dapat memperbarui kebijakan ini dari waktu ke waktu. Perubahan akan dipublikasikan di halaman ini dengan tanggal pembaruan terbaru.
      </p>
    </section>

    {{-- 13. Kontak --}}
    <section id="kontak" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">13. Kontak</h2>
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
          <div class="text-xs text-gray-500">Perjanjian Layanan</div>
          <a href="{{ url('/agreement') }}" class="font-semibold text-sky-700 underline">Baca di sini</a>
        </div>
      </div>
    </section>

    <div class="mt-8 rounded-xl border bg-slate-50 p-4 text-xs text-slate-700">
      <strong>Penafian:</strong> Kebijakan ini bersifat umum dan bukan nasihat hukum. Sesuaikan dengan operasi bisnis Anda
      dan, bila perlu, konsultasikan dengan penasihat hukum (mis. terkait UU PDP Indonesia).
    </div>
  </div>
</section>
@endsection
