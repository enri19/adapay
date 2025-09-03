<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', config('app.name', 'Hotspot Portal'))</title>

    {{-- Tailwind + font (sama seperti app) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme:{ extend:{ fontFamily:{ sans:['Inter','ui-sans-serif','system-ui'] } } } }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

    {{-- Komponen tombol (reuse dari app) --}}
    <style>
      :root{
        --b-50:#f0f9ff; --b-100:#e0f2fe;
        --b-600:#0284c7; --b-700:#0369a1;
        --gray-200:#e5e7eb; --gray-300:#e2e8f0; --gray-700:#374151;
        --header-h: 78px; /* mobile */
      }

      /* ===== Header ===== */
      .site-header{ height: var(--header-h); }
      .site-header .header__inner{
        height:100%; display:flex; align-items:center; justify-content:space-between;
      }

      /* Landing: fixed + overlay gelap transparan + blur (teks putih) */
      .header--fixed{ position:fixed; top:0; left:0; right:0; z-index:50; }
      .header--landing{
        background: linear-gradient(to bottom, rgba(2,8,23,.35), rgba(2,8,23,0.02));
        -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
        border-bottom: 0;
      }
      .header--landing a{ color:#fff; }

      /* App pages: sticky putih */
      .header--app{
        position:sticky; top:0; z-index:40;
        background:#fff; border-bottom:1px solid var(--gray-200);
      }

      /* OFFSET konten hanya jika header fixed (landing) */
      .has-fixed-header main{ padding-top: var(--header-h); }

      /* ===== Buttons (konsisten di semua halaman) ===== */
      .btn{
        display:inline-flex; align-items:center; justify-content:center; gap:.5rem;
        border-radius:12px; padding:.66rem 1rem;
        font-weight:700; font-size:.92rem;
        transition:transform .2s ease, filter .2s ease, box-shadow .2s ease, background .2s ease, border-color .2s ease;
        text-decoration:none; cursor:pointer;
      }
      .btn:hover{ filter:brightness(1.06); transform:translateY(-1px); }
      .btn:active{ transform:translateY(0); }
      .btn[disabled], .btn[aria-busy="true"]{ opacity:.7; cursor:not-allowed; transform:none; }

      /* Primary (biru solid) */
      .btn--primary{
        background:var(--b-600); color:#fff; border:1px solid transparent;
        box-shadow:0 8px 20px -10px rgba(2,132,199,.45);
      }
      .btn--primary:hover{ background:var(--b-700); }

      /* Secondary (untuk header/halaman putih) */
      .btn--secondary{
        background:#fff; color:#1e293b; border:1px solid var(--gray-300);
        box-shadow:0 2px 6px rgba(15,23,42,.06);
      }
      .btn--secondary:hover{
        background:#f9fafb; border-color:#cbd5e1; color:#0f172a;
        box-shadow:0 4px 8px rgba(15,23,42,.08);
      }

      /* Ghost khusus landing (teks putih di atas hero) */
      .-landing-landing{
        background:rgba(255,255,255,.12);
        color:#fff;
        border:1px solid rgba(255,255,255,.35);
        box-shadow:0 2px 6px rgba(0,0,0,.12);
      }
      .-landing-landing:hover{
        background:rgba(255,255,255,.20);
        border-color:rgba(255,255,255,.5);
      }
    </style>

    @stack('head')
  </head>

  {{-- Grid 3 baris: header (auto) • konten (1fr) • footer (auto) --}}
  <body class="min-h-svh grid grid-rows-[auto_1fr_auto] bg-white text-gray-800">
    @php
      // Anggap halaman welcome: root "/" (silakan sesuaikan ke route name kamu, mis. routeIs('welcome'))
      $isLanding = request()->is('/');
      $isBaseHost = strtolower(request()->getHost()) === 'pay.adanih.info';
    @endphp

    {{-- HEADER: logo + tombol TETAP ADA --}}
    <header class="site-header {{ $isLanding ? 'header--landing header--fixed' : 'header--app' }}">
      <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2">
          <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name','Hotspot Portal') }}"
               class="h-9 w-auto drop-shadow" onerror="this.replaceWith(document.createTextNode('AdaPay'))">
        </a>
        <nav class="flex items-center gap-3">
          <a href="{{ url('/hotspot') }}" class="btn btn--primary">Beli Voucher</a>
          @if ($isBaseHost)
            <a href="{{ route('hotspot.order.demo') }}" class="btn btn--ghost-landing">Coba Demo</a>
          @endif
        </nav>
      </div>
    </header>

    {{-- MAIN: konten landing (hero dsb). Hero biasanya full-bleed di bawah header absolute --}}
    <main class="w-full">
      @yield('content')
    </main>

    {{-- FOOTER: sama seperti layout app (URL TIDAK DIHILANGKAN) --}}
    <footer class="py-8 text-xs text-gray-500 border-t bg-white/70 backdrop-blur">
      <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>&copy; {{ date('Y') }} {{ config('app.name', 'Hotspot Portal') }}. All rights reserved.</div>
        <nav class="flex items-center gap-3">
          <a href="{{ route('agreement.show') }}" class="underline text-sky-700 hover:text-sky-900">Perjanjian Layanan</a>
          <span class="text-gray-300">•</span>
          <a href="{{ route('privacy.show') }}" class="underline text-sky-700 hover:text-sky-900">Kebijakan Privasi</a>
        </nav>
      </div>
    </footer>

    @stack('scripts')
  </body>
</html>
