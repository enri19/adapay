<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Admin')</title>
  <style>
    :root{
      --bg:#f8fafc; --card:#fff; --tx:#111827; --mut:#6b7280;
      --bd:#e5e7eb; --b:#2563eb; --b2:#1d4ed8; --ok:#10b981; --err:#ef4444; --warn:#f59e0b;
      --sideW: 240px;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--tx);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Arial}
    a{color:var(--b);text-decoration:none} a:hover{text-decoration:underline}

    .wrap{display:block;grid-template-columns:240px 1fr;min-height:100vh}
    .side{border-right:1px solid var(--bd);background:#fff}
    .brand{padding:20.5px;font-weight:800;border-bottom:1px solid var(--bd)}
    .nav a{display:block;padding:10px 16px;border-left:3px solid transparent;color:#374151}
    .nav a:hover{background:#f9fafb}
    .nav a.active{border-left-color:var(--b);background:#eff6ff;color:#1e3a8a}

    .top{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--bd);background:#fff;position:sticky;top:0;z-index:10}
    .main{padding:16px}
    .container{margin:0 auto}

    /* Cards */
    .card{background:var(--card);border:1px solid var(--bd);border-radius:.75rem;padding:16px}

    /* Buttons */
    .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem .9rem;border-radius:.55rem;border:1px solid var(--bd);background:#fff;font-weight:600}
    .btn--primary{background:var(--b);color:#fff;border-color:transparent}
    .btn--primary:hover{background:var(--b2)}
    .btn--ghost{color:var(--b);background:#fff}
    .btn[disabled]{opacity:.6;cursor:not-allowed}
    .spinner{width:1rem;height:1rem;border-radius:999px;border:.18rem solid rgba(255,255,255,.28);border-top-color:#fff;animation:spin .8s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}

    /* --- semua tombol jadi pointer --- */
    button,
    .btn,
    a.btn,
    .icon-btn,
    [role="button"],
    [type="button"],
    [type="submit"]{
      cursor: pointer;
      user-select: none;
      -webkit-user-select: none;
    }

    /* tombol yang disabled jangan pointer */
    button[disabled],
    .btn[disabled],
    [role="button"][aria-disabled="true"],
    [type="button"][disabled],
    [type="submit"][disabled]{
      cursor: not-allowed !important;
    }

    /* sidebar nempel kiri, tinggi penuh, bisa di-scroll */
    .side{
      position:fixed; left:0; top:0; bottom:0;
      width:var(--sideW);
      overflow:auto;
      border-right:1px solid var(--bd);
      background:#fff; z-index:30;
    }

    /* offset area konten agar tidak ketutup sidebar */
    .wrap > div{
      margin-left:var(--sideW);
      min-height:100vh;
      display:flex; flex-direction:column;
    }

    /* header tetap sticky di area konten */
    .top{ position:sticky; top:0; z-index:10; }

    /* responsif: di mobile, sidebar jadi normal (non-fixed) dan content full width */
    @media (max-width:900px){
      :root { --sideW: 0px; }
      .side{ position:static; width:auto; height:auto; }
      .wrap > div{ margin-left:0; }
      .nav{ display:flex; overflow:auto; }
      .nav a{ white-space:nowrap; }
    }

    /* Forms */
    .form{display:grid;gap:12px}
    .form-grid{display:grid;gap:12px}
    .form-2{grid-template-columns:repeat(2,minmax(0,1fr))}
    .label{display:block;font-size:.9rem;margin-bottom:.25rem;color:var(--tx)}
    .control{display:flex;align-items:center;border:1px solid var(--bd);border-radius:.55rem;background:#fff;padding:.55rem .65rem}
    .control:focus-within{border-color:var(--b);box-shadow:0 0 0 3px rgba(37,99,235,.15)}
    .input,.select,.textarea{border:0;outline:0;background:transparent;width:100%;font-size:.96rem;color:var(--tx)}
    .select{appearance:none}
    .help{font-size:.82rem;color:var(--mut);margin-top:.25rem}
    .row-actions{display:flex;gap:.5rem;margin-top:.5rem}

    /* Pills / badges */
    .pill{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .5rem;border-radius:999px;font-size:.78rem;border:1px solid var(--bd);background:#fff}
    .pill--ok{color:#065f46;background:#ecfdf5;border-color:#a7f3d0}
    .pill--off{color:#991b1b;background:#fef2f2;border-color:#fecaca}

    /* Tables */
    .table-wrap{overflow:auto;border:1px solid var(--bd);border-radius:.75rem;background:#fff}
    table.table{width:100%;border-collapse:separate;border-spacing:0}
    .table thead th{background:#f8fafc;font-weight:700;text-align:left;border-bottom:1px solid var(--bd);padding:.7rem .75rem;font-size:.9rem}
    .table tbody td{border-top:1px solid var(--bd);padding:.65rem .75rem;font-size:.92rem;vertical-align:top}
    .table tbody tr:hover{background:#fafafa}
    .table .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
    @media (max-width:900px){
      .wrap{grid-template-columns:1fr}
      .side{position:sticky;top:0;z-index:20}
      .nav{display:flex;overflow:auto}.nav a{white-space:nowrap}
      .form-2{grid-template-columns:1fr}
    }

    .flash{padding:.55rem .7rem;border-radius:.55rem;font-size:.9rem;margin-bottom:.75rem}
    .flash--ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .flash--err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

    .hidden{ display:none }
  </style>
  @stack('head')
</head>
<body>
  <div class="wrap">
    <aside class="side">
      <div class="brand">Admin Panel</div>
      @php
        $rname = optional(request()->route())->getName();
        $isClients  = \Illuminate\Support\Str::startsWith((string)$rname, 'clients.');
        $isVouchers = \Illuminate\Support\Str::startsWith((string)$rname, 'vouchers.');
        $isPays     = \Illuminate\Support\Str::startsWith((string)$rname, 'admin.payments.');
        $isOrders   = \Illuminate\Support\Str::startsWith((string)$rname, 'admin.orders.');
      @endphp

      <nav class="nav">
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard')?'active':'' }}">Dashboard</a>
        <a href="{{ route('clients.index') }}"  class="{{ $isClients?'active':'' }}">Clients</a>
        <a href="{{ route('admin.payments.index') }}" class="{{ $isPays?'active':'' }}">Payments</a>
        <a href="{{ route('admin.orders.index') }}"   class="{{ $isOrders?'active':'' }}">Orders</a>
        <a href="{{ route('vouchers.index') }}" class="{{ $isVouchers?'active':'' }}">Vouchers</a>
      </nav>
    </aside>
    <div>
      <header class="top">
        <div>@yield('title','Admin')</div>
        <form method="POST" action="{{ route('admin.logout') }}">
          @csrf
          <button class="btn btn--ghost">Logout</button>
        </form>
      </header>
      <main class="main">
        @if(session('ok')) <div class="flash flash--ok">{{ session('ok') }}</div> @endif
        @if(session('error')) <div class="flash flash--err">{{ session('error') }}</div> @endif
        @yield('content')
      </main>
    </div>
  </div>
  @stack('scripts')
  @push('scripts')
    <script>
      document.addEventListener('submit', function(e){
        const btn = e.target.querySelector('button[type="submit"]');
        if(!btn) return;
        const sp = btn.querySelector('.spinner'); const lb = btn.querySelector('.btn__label');
        btn.setAttribute('disabled','disabled'); if(sp) sp.classList.remove('hidden'); if(lb){ btn.__t=lb.textContent; lb.textContent='Menyimpan…'; }
        setTimeout(()=>{ if(lb&&btn.__t) lb.textContent=btn.__t; }, 4000); // fallback
      }, true);
    </script>
  @endpush
</body>
</html>
