<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', config('app.name', 'Hotspot Portal'))</title>

    <!-- Tailwind via CDN (cepat untuk prototipe) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] }
          }
        }
      }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

    <!-- ⬇️ PINDAHKAN style global KE DALAM HEAD -->
    <style>
      /* Palet biru minimal */
      :root{
        --b-50:#f0f9ff; --b-100:#e0f2fe;
        --b-600:#0284c7; --b-700:#0369a1;
        --gray-200:#e5e7eb; --gray-700:#374151; --green-500:#10b981;
      }

      /* Tombol dasar */
      .btn{
        display:inline-flex; align-items:center; gap:.5rem;
        padding:.6rem .9rem; border-radius:.5rem;
        font-weight:600; font-size:.92rem; line-height:1;
        border:1px solid var(--gray-200);
        background:#fff; color:var(--gray-700);
        transition: background-color .15s ease, border-color .15s ease, transform .06s ease, opacity .15s ease;
        user-select:none; -webkit-user-select:none;
      }
      .btn:hover{ background:#fafafa }
      .btn:active{ transform:translateY(1px) }
      .btn[disabled], .btn[aria-busy="true"]{ opacity:.7; cursor:not-allowed; transform:none }

      .btn--primary{ background:var(--b-600); color:#fff; border-color:transparent; }
      .btn--primary:hover{ background:var(--b-700) }
      .btn--ghost{ background:#fff; color:var(--b-700); border-color:var(--b-100); }
      .btn--ghost:hover{ background:var(--b-50); border-color:var(--b-100) }

      .spinner{
        width:1rem; height:1rem; border-radius:999px; flex:0 0 auto;
        border:.18rem solid rgba(255,255,255,.25);
        border-top-color: currentColor;
        animation: spin .8s linear infinite;
      }
      .btn--ghost .spinner{
        border-color: rgba(37,99,235,.25);
        border-top-color: #1d4ed8;
      }
      @keyframes spin { to { transform: rotate(360deg); } }

      .icon-check{ width:1rem; height:1rem; color:var(--green-500); }
      .copy-btn{
        display:inline-flex; align-items:center; gap:.25rem;
        padding:.25rem .4rem; border:1px solid #e5e7eb; border-radius:.4rem;
        background:#fff; color:#374151;
      }
      .copy-btn:hover{ background:#f9fafb }
      .copy-btn[disabled]{ opacity:.6; cursor:not-allowed }
      .ic{ width:16px; height:16px }
      .hidden{ display:none }

      /* Haluskan tampilan */
      body { font-feature-settings: "cv02","cv03","cv04","cv11"; }
    </style>

    @stack('head')
  </head>

  <!-- ⬇️ BODY sebagai grid 3-baris agar tinggi konten rapi -->
  <body class="min-h-svh grid grid-rows-[auto_1fr_auto] bg-gray-50 text-gray-800">
    <header class="border-b bg-white/90 backdrop-blur sticky top-0 z-40">
      @php
        // hanya sembunyikan di halaman persis /hotspot (bukan subpath)
        $showBuy = !request()->is('hotspot');
      @endphp

      <div class="max-w-3xl mx-auto px-4 h-14 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2">
          <img src="{{ asset('images/logo.png') }}"
              alt="{{ config('app.name', 'Hotspot Portal') }}"
              class="h-8 w-auto object-contain"
              onerror="this.replaceWith(document.createTextNode('Hotspot'))">
          <span class="font-semibold">{{ config('app.name', 'Hotspot Portal') }}</span>
        </a>

        @php $isBaseHost = strtolower(request()->getHost()) === 'pay.adanih.info'; @endphp

        @if ($showBuy)
          @if ($isBaseHost)
            <div class="flex items-center gap-2">
              <a href="{{ url('/hotspot') }}" class="btn btn--primary">Beli Voucher</a>
              <a href="{{ route('hotspot.order.demo') }}" class="btn btn--ghost">Coba Demo</a>
            </div>
          @else
            <a href="{{ url('/hotspot') }}" class="btn btn--primary">Beli Voucher</a>
          @endif
        @endif
      </div> <!-- ✅ TUTUP div container header -->
    </header>

    <!-- main otomatis mengisi ruang 1fr (tinggi fleksibel) -->
    <main class="max-w-3xl mx-auto px-4 py-6 w-full">
      {{-- Flash message --}}
      @if (session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
          {{ session('success') }}
        </div>
      @endif
      @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {{ session('error') }}
        </div>
      @endif

      @yield('content')
    </main>

    <footer class="py-8 text-xs text-gray-500 border-t bg-white/70 backdrop-blur">
      <div class="max-w-3xl mx-auto px-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>&copy; {{ date('Y') }} {{ config('app.name', 'Hotspot Portal') }}. All rights reserved.</div>
        <nav class="flex items-center gap-3">
          <a href="{{ route('agreement.show') }}" class="underline text-sky-700 hover:text-sky-900">Perjanjian Layanan</a>
          <span class="text-gray-300">•</span>
          <a href="{{ route('privacy.show') }}" class="underline text-sky-700 hover:text-sky-900">Kebijakan Privasi</a>
        </nav>
      </div>
    </footer>

    @include('partials.cookie-consent')
    @stack('scripts')
  </body>
</html>
