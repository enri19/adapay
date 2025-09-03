@extends('layouts.landing')
@section('title','AdaPay — Selamat Datang')

@push('head')
<style>
  #welcome-landing{
    --px: clamp(20px, 4vw, 48px);
    --py: clamp(56px, 7vw, 96px);

    /* base */
    --ink:#0f172a; --muted:#64748b;
    --bd:#e5e7eb; --bd2:#e2e8f0;

    /* azure (section gabungan 3–5) */
    --azure-3:#7dd3ff; --azure-4:#38bdf8; --azure-5:#0ea5e9; --azure-6:#0284c7;

    /* violet (CTA/7) */
    --violet-5:#8b5cf6; --violet-6:#6d28d9;

    /* glass */
    --glass-bg: rgba(255,255,255,.12);
    --glass-bd: rgba(255,255,255,.22);
    --glass-tx: #ffffff;
  }

  /* full-bleed stabil */
  #welcome-landing .full-bleed{
    width:100vw; margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw); position:relative;
  }
  #welcome-landing .band{ position:relative; isolation:isolate; padding:var(--py) var(--px); }
  #welcome-landing .band:first-child {
    padding-top: calc(var(--py) + var(--header-h));
  }
  #welcome-landing .wrap{ max-width:1120px; margin:0 auto; }

  /* layer dasar setiap band */
  #welcome-landing .band::before{
    content:""; position:absolute; inset:0; z-index:-2; background:#fff;
  }
  #welcome-landing .band::after{
    content:""; position:absolute; inset:0; z-index:-1; pointer-events:none;
    background-image:var(--motifs, none);
    background-repeat:no-repeat; background-size:140% 140%; background-position:center -10%;
    opacity:.95;
  }

  /* ---------------- HERO ---------------- */
  #welcome-landing .band--hero{ color:#fff; }
  #welcome-landing .band--hero .muted{ color:rgba(255,255,255,.8); }
  #welcome-landing .band--hero::before{ background: var(--azure-6); }

  /* -------------- PLAIN (putih) -------------- */
  #welcome-landing .band--plain::before{ background:#fff; }
  #welcome-landing .band--plain{ color:var(--ink); }

  /* ---------- AZURE (gabungan 3–5) ---------- */
  #welcome-landing .band--azure{ color:#fff; }
  #welcome-landing .band--azure::before{ background: var(--azure-6); }
  /* glass cards on azure */
  #welcome-landing .band--azure .card{
    background: var(--glass-bg); border-color: var(--glass-bd); color: var(--glass-tx);
    box-shadow: 0 1px 2px rgba(0,0,0,.06), 0 18px 40px -18px rgba(2,132,199,.55);
  }
  #welcome-landing .band--azure .muted{ color: rgba(255,255,255,.85); }
  #welcome-landing .band--azure a{ color:#e6f6ff; }
  #welcome-landing .band--azure .pill{
    background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.28); color:#fff;
  }
  #welcome-landing .band--azure .chip{
    display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .6rem; border-radius:999px;
    font-size:.75rem; font-weight:600; background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.25); color:#fff; backdrop-filter:saturate(120%) blur(2px);
  }

  /* ---------- VIOLET (CTA/section 7) ---------- */
  #welcome-landing .band--violet{ color:#fff; }
  #welcome-landing .band--violet::before{ background: var(--violet-6); }
  #welcome-landing .band--violet .card{
    background: var(--glass-bg); border-color: var(--glass-bd); color: var(--glass-tx);
    box-shadow: 0 1px 2px rgba(0,0,0,.06), 0 18px 40px -18px rgba(109,40,217,.55);
  }
  #welcome-landing .band--violet .muted{ color: rgba(255,255,255,.85); }
  
  /* Link & button text in violet section */
  #welcome-landing .band--violet a{
    color:#ffffff; /* lebih jelas di background violet */
  }
  #welcome-landing .band--violet .btn--secondary{
    background:#fff;
    color:#1e293b; /* slate-800 untuk teks tombol secondary */
    border:1px solid rgba(255,255,255,.25);
    box-shadow:0 2px 6px rgba(0,0,0,.1);
  }
  #welcome-landing .band--violet .btn--secondary:hover{
    background:#f9fafb;
    color:#0f172a;
    border-color:rgba(255,255,255,.4);
  }


  /* chip hero */
  #welcome-landing .chip{
    display:inline-flex; align-items:center; gap:.4rem;
    padding:.35rem .6rem;
    border-radius:999px;
    font-size:.75rem; font-weight:600;
    color:#fff;
    background:rgba(255,255,255,.10);
    border:1px solid rgba(255,255,255,.25);
    backdrop-filter:saturate(120%) blur(2px);
  }

  /* dot kecil */
  #welcome-landing .chip-dot{
    width:.5rem; height:.5rem; border-radius:999px;
  }

  /* varian warna dot */
  #welcome-landing .chip-dot.green{ background:#22c55e; }
  #welcome-landing .chip-dot.blue{ background:#38bdf8; }
  #welcome-landing .chip-dot.pink{ background:#e879f9; }

  /* ------ components shared ------ */
  #welcome-landing .card{
    position:relative; border-radius:16px; padding:16px; border:1px solid var(--bd);
    background:#fff;
    box-shadow:0 1px 2px rgba(15,23,42,.04), 0 12px 24px -12px rgba(15,23,42,.12);
    transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  }
  #welcome-landing .card:hover{
    transform:translateY(-2px);
    box-shadow:0 2px 6px rgba(15,23,42,.06), 0 20px 32px -16px rgba(15,23,42,.16);
    border-color:#dfe3e8;
  }
  #welcome-landing .ribbon{
    height:4px;
    border-radius:999px;
    width:120px;
    margin-top:6px;
    background:linear-gradient(90deg, var(--azure-5), var(--azure-6));
  }

  #welcome-landing .pill{
    display:inline-block; padding:.32rem .6rem; border-radius:8px; font-weight:600; font-size:.8rem;
    background:#f8fafc; border:1px solid var(--bd2); color:var(--ink);
  }
  #welcome-landing .muted{ color:var(--muted); }
  
  /* Inline code style */
  #welcome-landing code{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    padding:.12rem .4rem;
    border-radius:.4rem;
    font-size:.82em;
    font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    color:#0ea5e9;
  }

  /* Monochrome icon for colored bands (elegan, no rainbow) */
  #welcome-landing .icon-wrap.icon-wrap--mono{
    width:28px; height:28px; border-radius:999px;
    display:inline-flex; align-items:center; justify-content:center;
    color:#fff;
    background:rgba(255,255,255,.18);
    border:1px solid rgba(255,255,255,.28);
    box-shadow:0 6px 18px rgba(0,0,0,.07);
  }

  /* Buttons (base) */
  #welcome-landing .btn{
    display:inline-flex; align-items:center; justify-content:center; gap:.5rem;
    border-radius:12px; padding:.66rem 1rem;
    font-weight:700; font-size:.92rem;
    transition:transform .2s ease, filter .2s ease, box-shadow .2s ease, background .2s ease;
    will-change:transform;
    text-decoration:none;
    cursor:pointer;
  }

  /* Primary */
  #welcome-landing .btn--primary{
    background: var(--azure-5);
    color:#fff;
    border:1px solid rgba(255,255,255,.35);
    box-shadow:0 8px 20px -10px rgba(14,165,233,.6);
  }
  #welcome-landing .btn--primary:hover{
    filter:brightness(1.06); transform:translateY(-1px);
  }
  #welcome-landing .btn--primary:active{ transform:translateY(0); }

  /* Secondary */
  #welcome-landing .btn--secondary{
    background:#fff;
    color:#1e293b;
    font-weight:600;
    border:1px solid var(--bd2);
    box-shadow:0 2px 6px rgba(15,23,42,.06);
  }
  #welcome-landing .btn--secondary:hover{
    background:#f9fafb;
    border-color:#cbd5e1;
    color:#0f172a;
    transform:translateY(-1px);
    box-shadow:0 4px 8px rgba(15,23,42,.08);
  }
  #welcome-landing .btn--secondary:active{ transform:translateY(0); }

  /* Focus state (shared) */
  #welcome-landing .btn:focus-visible{
    outline:3px solid rgba(14,165,233,.4);
    outline-offset:2px;
  }

  /* Pastikan aturan khusus ini diletakkan SETELAH aturan .btn:hover yang umum */
  #welcome-landing .btn.btn--track{
    border:1px solid rgba(255,255,255,.18);
    background:linear-gradient(180deg,rgba(255,255,255,.16),rgba(255,255,255,.06));
    -webkit-backdrop-filter: blur(6px);
    backdrop-filter: blur(6px);
    color:#fff;
    transition: transform .2s ease, filter .2s ease, background .2s ease, border-color .2s ease;
  }

  #welcome-landing .btn.btn--track:hover{
    /* bikin perubahan yang terlihat saat hover */
    border-color: rgba(255,255,255,.35);
    background:linear-gradient(180deg,rgba(255,255,255,.22),rgba(255,255,255,.10));
    filter: brightness(1.06);
    transform: translateY(-1px); /* kasih sedikit “lift” */
  }

  #welcome-landing .btn.btn--track:active{
    transform: translateY(0);
  }

  /* Pastikan grid item FAQ tidak ikut stretch */
  #welcome-landing .faq-grid{
    align-items:start; /* penting */
  }
  #welcome-landing .faq-grid details{
    height:auto; /* biar expand sesuai isi sendiri */
  }

  /* Badge base */
  #welcome-landing .badge{
    display:inline-flex; align-items:center; gap:.4rem;
    border-radius:999px;
    padding:.32rem .9rem;
    font-size:.75rem; font-weight:700;
  }

  /* Mitra → biru jelas */
  #welcome-landing .badge--mitra{
    background:#0284c7;   /* biru kuat */
    color:#ffffff;
    border:1px solid #0369a1;
  }

  /* Member → hijau jelas */
  #welcome-landing .badge--member{
    background:#059669;   /* hijau emerald kuat */
    color:#ffffff;
    border:1px solid #047857;
  }

  /* Bedakan juga border card */
  #welcome-landing .card--mitra{
    border:2px solid #0284c7;
  }
  #welcome-landing .card--member{
    border:2px solid #059669;
  }

  /* Spinner (for loading inside button) */
  #welcome-landing .spinner{
    width:16px; height:16px;
    border-radius:999px;
    border:2px solid rgba(255,255,255,.6);
    border-top-color:transparent;
    animation:spin 1s linear infinite;
  }
  @keyframes spin{ to{ transform:rotate(360deg) } }

  /* reduce motion */
  @media (prefers-reduced-motion: reduce){
    #welcome-landing .card, #welcome-landing .btn, #welcome-landing .band::after{ transition:none !important }
    #welcome-landing .card:hover, #welcome-landing .btn:hover{ transform:none !important }
  }
</style>
@endpush

@section('content')
<div id="welcome-landing">

  {{-- 1. HERO (paling berwarna) --}}
  <section class="full-bleed band band--hero text-white">
    <div class="wrap">
      <div class="grid gap-6 md:grid-cols-2 md:items-center">
        <div>
          <div class="flex items-center gap-3 mb-5">
            <img src="{{ asset('logo.png') }}" alt="AdaPay" class="h-9 w-auto rounded-md" onerror="this.style.display='none'">
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">AdaPay</h1>
          </div>

          <div class="flex flex-wrap items-center gap-2 mb-4">
            <span class="chip">
              <i class="chip-dot green"></i> Instan
            </span>
            <span class="chip">
              <i class="chip-dot blue"></i> Auto-Provision MikroTik
            </span>
            <span class="chip">
              <i class="chip-dot pink"></i> Webhook Realtime
            </span>
          </div>

          <p class="text-white/95 leading-relaxed">
            Portal pembayaran <strong>voucher hotspot</strong> yang ringkas, cepat, dan andal.
            Dukung <b>QRIS</b>, <b>GoPay</b>, <b>ShopeePay</b>, multi-tenant via subdomain, serta provisioning otomatis ke MikroTik.
          </p>

          <div class="mt-6 flex flex-wrap items-center gap-3">
            <a href="{{ url('/hotspot') }}" class="btn btn--primary">
              <span class="btn__label">Beli Voucher</span>
              <span class="spinner hidden" aria-hidden="true"></span>
            </a>
            <a href="{{ url('/orders/track') }}"
               class="btn btn--track">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a5 5 0 0 1 5 5c0 4.25-5 11-5 11S7 11.25 7 7a5 5 0 0 1 5-5Zm0 7.5A2.5 2.5 0 1 0 12 4a2.5 2.5 0 0 0 0 5.5ZM5 20.5a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Z"/></svg>
              Lacak Pesanan
            </a>
          </div>

          <div class="mt-7 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl px-3 py-2" style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22)">
              <div class="text-xs text-white/80">Rata waktu aktivasi</div>
              <div class="mt-1 text-lg font-semibold">≈ 2–5 detik</div>
            </div>
            <div class="rounded-xl px-3 py-2" style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22)">
              <div class="text-xs text-white/80">Tingkat sukses pembayaran</div>
              <div class="mt-1 text-lg font-semibold">99%*</div>
            </div>
            <div class="rounded-xl px-3 py-2" style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22)">
              <div class="text-xs text-white/80">Uptime (30 hari)</div>
              <div class="mt-1 text-lg font-semibold">99.9%</div>
            </div>
          </div>
          <p class="mt-2 text-[.78rem] text-white/70">*Bergantung kanal & payment partner.</p>
        </div>

        {{-- Ilustrasi SVG --}}
        <div class="md:justify-self-end">
          <svg viewBox="0 0 560 360" role="img" aria-labelledby="il-title il-desc"
               class="w-full h-auto max-w-[560px] rounded-xl"
               style="background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.22)">
            <title id="il-title">Ilustrasi alur AdaPay</title>
            <desc id="il-desc">Ponsel scan QRIS, pembayaran sukses, user hotspot terprovisi ke router MikroTik.</desc>
            <defs>
              <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse">
                <path d="M20 0H0V20" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="1"/>
              </pattern>
              <linearGradient id="grad" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#e0f2fe" stop-opacity=".9"/>
                <stop offset="100%" stop-color="#bae6fd" stop-opacity=".6"/>
              </linearGradient>
              <marker id="arrow" markerWidth="10" markerHeight="8" refX="8" refY="4" orient="auto">
                <path d="M0,0 L10,4 L0,8 z" fill="rgba(255,255,255,.9)"/>
              </marker>
            </defs>
            <rect x="0" y="0" width="560" height="360" fill="url(#grid)"/>
            <g transform="translate(60,50)">
              <rect x="0" y="0" rx="24" ry="24" width="180" height="260" fill="white" opacity=".95"/>
              <rect x="12" y="12" rx="18" ry="18" width="156" height="236" fill="url(#grad)" opacity=".85"/>
              <rect x="42" y="52" width="96" height="96" fill="#0ea5e9" opacity=".12" stroke="#0ea5e9"/>
              <rect x="50" y="60" width="16" height="16" fill="#0ea5e9"/>
              <rect x="74" y="60" width="10" height="10" fill="#0ea5e9"/>
              <rect x="96" y="60" width="16" height="16" fill="#0ea5e9"/>
              <rect x="50" y="84" width="10" height="10" fill="#0ea5e9"/>
              <rect x="74" y="84" width="16" height="16" fill="#0ea5e9"/>
              <rect x="96" y="84" width="10" height="10" fill="#0ea5e9"/>
              <rect x="50" y="108" width="16" height="16" fill="#0ea5e9"/>
              <rect x="74" y="108" width="10" height="10" fill="#0ea5e9"/>
              <rect x="96" y="108" width="16" height="16" fill="#0ea5e9"/>
              <rect x="36" y="170" rx="10" ry="10" width="108" height="22" fill="#0284c7" opacity=".9"/>
              <text x="90" y="185" text-anchor="middle" font-family="ui-sans-serif,system-ui" font-size="12" fill="#fff">Bayar</text>
            </g>
            <g transform="translate(280,70)">
              <rect x="0" y="0" rx="14" ry="14" width="220" height="80" fill="white" opacity=".95"/>
              <circle cx="28" cy="40" r="14" fill="#10b981"/>
              <path d="M21 40l6 6 10-12" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
              <text x="56" y="36" font-family="ui-sans-serif,system-ui" font-size="14" fill="#065f46" font-weight="700">Pembayaran Berhasil</text>
              <text x="56" y="56" font-family="ui-sans-serif,system-ui" font-size="12" fill="#475569">Otomatis aktif dalam hitungan detik</text>
            </g>
            <g transform="translate(300,180)">
              <rect x="0" y="0" rx="12" ry="12" width="200" height="120" fill="white" opacity=".95"/>
              <rect x="14" y="22" width="20" height="20" rx="4" fill="#0ea5e9"/>
              <rect x="42" y="22" width="20" height="20" rx="4" fill="#38bdf8"/>
              <rect x="70" y="22" width="20" height="20" rx="4" fill="#7dd3fc"/>
              <text x="14" y="74" font-family="ui-sans-serif,system-ui" font-size="14" fill="#0f172a" font-weight="700">Router MikroTik</text>
              <text x="14" y="94" font-family="ui-sans-serif,system-ui" font-size="12" fill="#475569">User hotspot diprovisi otomatis</text>
            </g>
            <path d="M240,130 C260,130 270,130 280,130" stroke="rgba(255,255,255,.9)" stroke-width="2" fill="none" marker-end="url(#arrow)"/>
            <path d="M390,160 C390,170 390,175 390,180" stroke="rgba(255,255,255,.9)" stroke-width="2" fill="none" marker-end="url(#arrow)"/>
          </svg>
        </div>
      </div>
    </div>
  </section>

  {{-- 2. METODE PEMBAYARAN (band genap = tinted + ribbon) --}}
  <section class="full-bleed band">
    <div class="wrap">
      <h2 class="text-xl font-bold">Metode Pembayaran</h2>
      <div class="ribbon"></div>
      <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
        <span class="pill" style="background:#e0f2fe;border-color:#bae6fd;color:#075985">QRIS (semua bank/e-wallet)</span>
        <span class="pill" style="background:#dcfce7;border-color:#bbf7d0;color:#065f46">GoPay</span>
        <span class="pill" style="background:#fff7ed;border-color:#ffedd5;color:#7c2d12">ShopeePay</span>
      </div>
      <p class="mt-2 text-sm muted">Nominal presisi, status real-time, dan <em>expiry</em> otomatis.</p>
    </div>
  </section>

  {{-- 3. GABUNGAN: Monitoring • Keamanan • SLA + Kenapa + Integrasi Teknis (AZURE) --}}
  <section class="full-bleed band band--azure">
    <div class="wrap">

      {{-- 3A. Monitoring • Keamanan • SLA --}}
      <div class="grid gap-4 md:grid-cols-3">
        <div class="card">
          <div class="flex items-center gap-2">
            <span class="icon-wrap icon-wrap--mono">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M3 12a9 9 0 1 1 18 0v4a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-4Z"/>
              </svg>
            </span>
            <h3 class="font-semibold">Monitoring & Status</h3>
          </div>
          <p class="mt-1 text-sm muted">Dipantau 24/7. Auto-retry & saran alternatif jika kanal bermasalah.</p>
          <a href="{{ url('/status') }}" class="mt-2 inline-flex text-xs underline">Lihat status live</a>
        </div>

        <div class="card">
          <div class="flex items-center gap-2">
            <span class="icon-wrap icon-wrap--mono">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 1a4 4 0 0 1 4 4v3H8V5a4 4 0 0 1 4-4Z"/><path d="M6 8h12a2 2 0 0 1 2 2v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-8a2 2 0 0 1 2-2Z"/>
              </svg>
            </span>
            <h3 class="font-semibold">Keamanan</h3>
          </div>
          <ul class="mt-1 list-disc pl-5 text-sm muted space-y-1">
            <li>Webhook signature (HMAC SHA-512)</li>
            <li>Server Key aman — tidak di browser</li>
            <li>Callback per-tenant & IP allowlist</li>
          </ul>
        </div>

        <div class="card">
          <div class="flex items-center gap-2">
            <span class="icon-wrap icon-wrap--mono">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v9a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V6Z"/>
              </svg>
            </span>
            <h3 class="font-semibold">SLA & Dukungan</h3>
          </div>
          <ul class="mt-1 list-disc pl-5 text-sm muted space-y-1">
            <li>Uptime target 99.9% (30 hari)</li>
            <li>Konfirmasi &lt; 5 detik</li>
            <li>Support WhatsApp & email (jam kerja)</li>
          </ul>
        </div>
      </div>

      {{-- pemisah kecil --}}
      <div class="mt-12"></div>

      {{-- 3B. Kenapa Memilih AdaPay? --}}
      <div class="mt-6">
        <h2 class="text-xl font-bold">Kenapa Memilih AdaPay?</h2>
        <div class="mt-3 grid gap-6 md:grid-cols-2">
          <ul class="list-disc space-y-1 pl-5 text-sm muted">
            <li><strong>Setup cepat</strong>: cukup atur subdomain & kredensial pembayaran.</li>
            <li><strong>Tanpa training</strong>: pelanggan tinggal scan & bayar.</li>
            <li><strong>Anti salah harga</strong>: nominal terkunci di invoice.</li>
            <li><strong>Auto-expire</strong>: transaksi kadaluarsa dibatalkan otomatis.</li>
          </ul>
          <ul class="list-disc space-y-1 pl-5 text-sm muted">
            <li><strong>Log lengkap</strong>: audit trail transaksi & webhook.</li>
            <li><strong>Scalable</strong>: multi-tenant, ribuan pesanan/hari.</li>
            <li><strong>Terintegrasi</strong>: MikroTik API, WhatsApp/SMS, & laporan.</li>
            <li><strong>Efisien biaya</strong>: cocok untuk UMKM sampai enterprise.</li>
          </ul>
        </div>
      </div>

      {{-- pemisah kecil --}}
      <div class="mt-12"></div>

      {{-- 3C. Integrasi Teknis --}}
      <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="card">
          <div class="flex items-center gap-2">
            <span class="icon-wrap icon-wrap--mono">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M4 4h16v6H4z"/><path d="M4 12h16v8H4z"/>
              </svg>
            </span>
            <h3 class="text-lg font-semibold">Integrasi Teknis — Webhook</h3>
          </div>
          <p class="mt-1 text-sm muted">
            Kirim ke <code>/webhook/payment/{client}</code> dengan header signature. Idempotent & aman.
          </p>
        </div>

        <div class="card">
          <div class="flex items-center gap-2">
            <span class="icon-wrap icon-wrap--mono">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M3 7h18v10H3z"/><path d="M7 11h10v2H7z" fill="#fff"/>
              </svg>
            </span>
            <h3 class="text-lg font-semibold">Integrasi Teknis — MikroTik</h3>
          </div>
          <p class="mt-1 text-sm muted">
            Mendukung <code>/ip/hotspot/user</code> via API. Mode push langsung atau pull oleh agen.
          </p>
        </div>
      </div>

    </div>
  </section>

  {{-- 4. FAQ (band genap = tinted + card accent) --}}
  <section class="full-bleed band">
    <div class="wrap">
      <h2 class="text-xl font-bold mb-2">FAQ</h2>
      <div class="ribbon"></div>
      <div class="grid gap-3 md:grid-cols-2 mt-3 faq-grid">
        <details class="card [&_summary]:cursor-pointer">
          <summary class="text-sm font-medium">Butuh aplikasi khusus untuk bayar?</summary>
          <div class="mt-1.5 text-sm muted">Tidak. Cukup scan <strong>QRIS</strong> dari m-banking/e-wallet atau klik deeplink GoPay/ShopeePay.</div>
        </details>
        <details class="card [&_summary]:cursor-pointer">
          <summary class="text-sm font-medium">Voucher aktif berapa lama setelah bayar?</summary>
          <div class="mt-1.5 text-sm muted">Biasanya &lt; 5 detik sejak pembayaran terkonfirmasi.</div>
        </details>
        <details class="card [&_summary]:cursor-pointer">
          <summary class="text-sm font-medium">Bisa kirim user via WhatsApp?</summary>
          <div class="mt-1.5 text-sm muted">Bisa, jika integrasi notifikasi diaktifkan per-client.</div>
        </details>
        <details class="card [&_summary]:cursor-pointer">
          <summary class="text-sm font-medium">Apakah mendukung multi-tenant?</summary>
          <div class="mt-1.5 text-sm muted">Ya. Mapping tenant via subdomain, dengan branding & price list per client.</div>
        </details>
      </div>
    </div>
  </section>

  {{-- 5) CTA GANDA — "Jualan..." & "Beli..." — Tone LILAC (2/2) --}}
  <section class="full-bleed band band--violet">
    <div class="wrap grid gap-6 md:grid-cols-2">
      {{-- Mitra --}}
      <div class="card">
        <div class="badge badge--mitra">Untuk Pemilik Hotspot / Mitra</div>
        <h3 class="mt-3 text-xl font-bold">Jualan voucher jadi secepat scan.</h3>
        <div class="ribbon"></div>
        <p class="mt-2 muted">Terima <strong>QRIS, GoPay, ShopeePay</strong>, status bayar realtime, dan <em>auto-provision</em> MikroTik. Multi-tenant via subdomain, laporan rapi, operasional efisien.</p>
        <ul class="mt-4 list-disc ml-5 text-sm muted space-y-1">
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
            class="btn btn--secondary">
            Chat Sales
          </a>
        </div>
      </div>

      {{-- Pelanggan --}}
      <div class="card">
        <div class="badge badge--member">Untuk Member / Pelanggan</div>
        <h3 class="mt-3 text-xl font-bold">Beli voucher? Tinggal scan.</h3>
        <div class="ribbon"></div>
        <p class="mt-2 muted">Bayar dengan <strong>QRIS & e-wallet</strong>, voucher aktif otomatis, kredensial tampil langsung di halaman pesanan.</p>
        <ul class="mt-4 list-disc ml-5 text-sm muted space-y-1">
          <li>Pembayaran aman & cepat</li>
          <li>Status realtime—tanpa menunggu</li>
          <li>Harga transparan, tanpa biaya tersembunyi</li>
        </ul>
        <div class="mt-5 flex flex-wrap gap-2">
          <a href="{{ url('/hotspot') }}" class="btn btn--primary"><span class="btn__label">Beli Voucher</span></a>
          <a href="{{ url('/orders/track') }}" class="btn btn--secondary">Lacak Pesanan</a>
        </div>
      </div>
    </div>
  </section>

</div>
@endsection
