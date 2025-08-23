<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin</title>
  <style>
    :root{ --bd:#e5e7eb; --bg:#f8fafc; --tx:#111827; --mut:#6b7280; --b:#2563eb; --b2:#1d4ed8; --ok:#10b981; --err:#ef4444; }
    *{ box-sizing:border-box }
    body{ margin:0; background:var(--bg); color:var(--tx); font-family:system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Arial }
    .wrap{ min-height:100vh; display:grid; place-items:center; padding:24px }
    .card{ width:100%; max-width:420px; background:#fff; border:1px solid var(--bd); border-radius:.8rem; padding:20px 20px 16px }
    .brand{ font-weight:800; font-size:1.05rem; margin-bottom:6px }
    h1{ margin:.25rem 0 1rem; font-size:1.4rem }
    label{ display:block; font-size:.9rem; margin:.6rem 0 .35rem }
    
    /* util */
    .hidden{ display:none !important; }

    /* grup input */
    .in{ display:flex; align-items:center; gap:.5rem; border:1px solid var(--bd); border-radius:.55rem; background:#fff; padding:.55rem .65rem }
    .in:focus-within{ border-color:var(--b); box-shadow:0 0 0 3px rgba(37,99,235,.15) }
    .in input{ border:0; outline:0; width:100%; font-size:.98rem; background:transparent; color:var(--tx) }

    /* tombol mata */
    .in--pw .icon-btn{
      margin-right:-.3rem; /* rapat ke sisi kanan */
      width:40px; padding:0;
      display:grid; place-items:center;
      background:#fff; color:var(--mut); cursor:pointer;
    }
    .in--pw .icon-btn:hover{ color:var(--tx) }
    .in--pw .icon-btn:focus{ outline:none }
    .in--pw .icon-btn:focus-visible{ box-shadow:0 0 0 3px rgba(37,99,235,.25) }
    .in--pw svg{ pointer-events:none }

    /* spinner (hidden by default via .hidden) */
    .spinner{
      width:1rem; height:1rem; border-radius:999px;
      border:.18rem solid rgba(255,255,255,.28);
      border-top-color:#fff; animation:spin .8s linear infinite;
    }
    @keyframes spin{ to{ transform:rotate(360deg) } }


    .icon-btn{ border:0; background:transparent; cursor:pointer; color:var(--mut) }
    .icon-btn:hover{ color:var(--tx) }
    .row{ display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:.6rem }
    .checkbox{ display:flex; align-items:center; gap:.5rem; font-size:.9rem }
    .btn{ display:inline-flex; align-items:center; gap:.5rem; padding:.65rem 1rem; border-radius:.6rem; border:1px solid transparent; font-weight:700; background:var(--b); color:#fff }
    .btn:hover{ background:var(--b2) }
    .btn[disabled]{ opacity:.7; cursor:not-allowed }
    .spinner{ width:1rem; height:1rem; border-radius:999px; border:.18rem solid rgba(255,255,255,.28); border-top-color:#fff; animation:spin .8s linear infinite; }
    @keyframes spin{ to{ transform:rotate(360deg) } }
    .help{ font-size:.85rem; color:var(--mut); margin-top:.6rem }
    .flash{ padding:.55rem .7rem; border-radius:.55rem; font-size:.9rem; margin:.5rem 0 }
    .flash--err{ background:#fef2f2; color:#991b1b; border:1px solid #fecaca }
    .flash--ok{ background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0 }
    .errors{ margin:.5rem 0; padding:.55rem .7rem; border-radius:.55rem; background:#fff7ed; color:#7c2d12; border:1px solid #fed7aa; font-size:.9rem }
    .errors ul{ margin:.25rem 0 0 1rem }
    .foot{ margin-top:.9rem; display:flex; justify-content:center; gap:12px; font-size:.9rem }
    a{ color:var(--b); text-decoration:none } a:hover{ text-decoration:underline }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">Admin Panel</div>
      <h1>Masuk</h1>

      @if(session('error')) <div class="flash flash--err">{{ session('error') }}</div> @endif
      @if(session('ok')) <div class="flash flash--ok">{{ session('ok') }}</div> @endif
      @if ($errors->any())
        <div class="errors">
          <strong>Periksa lagi:</strong>
          <ul>
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
          </ul>
        </div>
      @endif

      <form id="loginForm" method="POST" action="{{ route('admin.login.post') }}" autocomplete="on">
        @csrf

        <label for="email">Email</label>
        <div class="in">
          <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="admin@example.com" required autocomplete="username">
        </div>

        <label for="password">Password</label>
        <div class="in in--pw">
          <input id="password" type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
          <button type="button" class="icon-btn" id="togglePw" aria-pressed="false" aria-label="Tampilkan password">
            <svg id="ico-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="12" cy="12" r="3" stroke-width="2"/>
            </svg>
            <svg id="ico-eye-off" class="hidden" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M3 3l18 18" stroke-width="2"/>
              <path d="M10.58 10.58A2 2 0 0 0 12 14a2 2 0 0 0 1.42-.59" stroke-width="2"/>
              <path d="M16.24 7.76A10.94 10.94 0 0 1 23 12s-4 7-11 7a10.94 10.94 0 0 1-7.76-3.24" stroke-width="2"/>
              <path d="M6.53 6.53A10.94 10.94 0 0 0 1 12s4 7 11 7c1.66 0 3.24-.32 4.68-.91" stroke-width="2"/>
            </svg>
          </button>
        </div>


        <div class="row">
          <label class="checkbox">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
            <span>Ingat saya</span>
          </label>
          {{-- <a href="#" class="help">Lupa password?</a> --}}
        </div>

        <div style="margin-top:12px">
          <button id="btnLogin" class="btn" type="submit">
            <span class="btn__label">Masuk</span>
            <span class="spinner hidden" aria-hidden="true"></span>
          </button>
        </div>

        <div class="help">Gunakan akun admin. Kontak dev jika lupa password.</div>
      </form>

      <div class="foot">
        <a href="{{ url('/') }}">← Kembali ke situs</a>
      </div>
    </div>
  </div>

  <script>
    (function(){
      // toggle password
      var toggle = document.getElementById('togglePw');
      var pw = document.getElementById('password');
      var eye = document.getElementById('ico-eye');
      var eyeOff = document.getElementById('ico-eye-off');
      function swap(){
        var isPwd = pw.getAttribute('type') === 'password';
        pw.setAttribute('type', isPwd ? 'text' : 'password');
        eye.classList.toggle('hidden', !isPwd);
        eyeOff.classList.toggle('hidden', isPwd);
      }
      toggle && toggle.addEventListener('click', swap);

      // loading state submit
      var form = document.getElementById('loginForm');
      var btn = document.getElementById('btnLogin');
      form && form.addEventListener('submit', function(){
        var label = btn.querySelector('.btn__label');
        var spin = btn.querySelector('.spinner');
        btn.setAttribute('disabled', 'disabled');
        if (label){ btn.__orig = label.textContent; label.textContent = 'Memproses…'; }
        if (spin){ spin.classList.remove('hidden'); }
      });

      // fokus awal
      var email = document.getElementById('email');
      if (email && !email.value) email.focus();
      else if (pw) pw.focus();
    })();
  </script>
</body>
</html>
