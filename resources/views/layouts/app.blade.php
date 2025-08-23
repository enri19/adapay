<!doctype html>
<html lang="id">
  <style>
    /* Palet biru minimal */
    :root {
      --b-50:#eff6ff; --b-100:#dbeafe; --b-600:#2563eb; --b-700:#1d4ed8;
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

    /* Variasi minimal */
    .btn--primary{
      background:var(--b-600); color:#fff; border-color:transparent;
    }
    .btn--primary:hover{ background:var(--b-700) }
    .btn--ghost{
      background:#fff; color:var(--b-700); border-color:var(--b-100);
    }
    .btn--ghost:hover{ background:var(--b-50); border-color:var(--b-100) }

    /* Spinner minimal — track pucat, kepala pakai currentColor */
    .spinner{
      width:1rem; height:1rem; border-radius:999px; flex:0 0 auto;
      border:.18rem solid rgba(255,255,255,.25);  /* track pucat (pas untuk .btn--primary biru) */
      border-top-color: currentColor;             /* kepala (ikut warna teks tombol) */
      animation: spin .8s linear infinite;
    }
    /* Kalau dipakai di tombol ghost (teks biru di background putih), pakai track biru pucat */
    .btn--ghost .spinner{
      border-color: rgba(37,99,235,.25);
      border-top-color: #1d4ed8; /* biru */
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Ikon ceklis untuk Copy link */
    .icon-check{ width:1rem; height:1rem; color:var(--green-500); }

    .hidden{ display:none }
  </style>

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

    <!-- Alpine (opsional untuk menu mobile) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
      /* Haluskan tampilan */
      body { font-feature-settings: "cv02","cv03","cv04","cv11"; }
    </style>
    @stack('head')
  </head>
  <body class="bg-gray-50 text-gray-800">
    <header class="border-b bg-white/90 backdrop-blur sticky top-0 z-40">
      <div class="max-w-3xl mx-auto px-4 h-14 flex items-center justify-between">
        <a href="{{ url('/hotspot') }}" class="flex items-center gap-2">
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-blue-600 text-white font-semibold">HS</span>
          <span class="font-semibold">{{ config('app.name', 'Hotspot Portal') }}</span>
        </a>
        <nav class="hidden md:flex items-center gap-4">
          <a href="{{ url('/hotspot') }}" class="text-sm hover:text-blue-600">Beli Voucher</a>
        </nav>
        <div class="md:hidden" x-data="{open:false}">
          <button class="p-2 rounded-lg hover:bg-gray-100" @click="open=!open" aria-label="Menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div x-show="open" @click.outside="open=false" class="absolute left-0 right-0 top-14 bg-white border-b md:hidden">
            <a href="{{ url('/hotspot') }}" class="block px-4 py-3 text-sm hover:bg-gray-50">Beli Voucher</a>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-6">
      {{-- Flash message (opsional) --}}
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

    <footer class="py-8 text-center text-xs text-gray-500">
      &copy; {{ date('Y') }} {{ config('app.name', 'Hotspot Portal') }}. All rights reserved.
    </footer>

    @stack('scripts')
  </body>
</html>
