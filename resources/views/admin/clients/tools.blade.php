@extends('layouts.admin')
@section('title','Router & Hotspot Tools')

@push('head')
<style>
  /* posisi relatif supaya overlay nempel */
  .tool-card{ position:relative; }

  /* overlay loader */
  .loader-overlay{
    position:absolute; inset:0; border-radius:.75rem;
    background:rgba(255,255,255,.75);
    display:flex; align-items:center; justify-content:center;
    z-index:20; backdrop-filter:saturate(120%) blur(1px);
    opacity:0; pointer-events:none; transition:opacity .15s ease;
  }
  .tool-card.is-loading .loader-overlay{ opacity:1; pointer-events:auto; }

  /* spinner besar */
  .loader{
    display:flex; align-items:center; gap:.6rem; font-weight:600; color:#374151;
    font-size:.95rem;
  }
  .loader .ring{
    width:22px; height:22px; border-radius:999px;
    border:3px solid rgba(0,0,0,.15); border-top-color:var(--b);
    animation:spin .8s linear infinite;
  }
  @keyframes spin{ to{ transform:rotate(360deg) } }
</style>
@endpush

@section('content')
<div class="container">
  <div class="card" style="margin-bottom:12px">
    <div style="font-weight:700">Router & Hotspot Tools</div>
    <div class="help">Client: <span class="mono">{{ $client->client_id }}</span> — {{ $client->name }}</div>
  </div>

  <div class="form-grid form-2">
    {{-- Kartu: Test Koneksi Router --}}
    <div class="card tool-card">
      <div class="loader-overlay" aria-hidden="true">
        <div class="loader"><span class="ring"></span><span class="txt">Memproses…</span></div>
      </div>
      
      <div style="font-weight:700;margin-bottom:.25rem">Test Koneksi Router</div>
      <div class="help">
        {{ $client->router_host ?: '—' }}:{{ $client->router_port ?: 8728 }} — user: {{ $client->router_user ?: '—' }}
      </div>
      <form method="POST" action="{{ route('admin.clients.router.test',$client) }}" style="margin-top:.75rem">
        @csrf
        <button class="btn btn--primary">Jalankan Test</button>
      </form>
    </div>

    {{-- Kartu: Buat User Hotspot (Test) --}}
    <div class="card tool-card">
      <div class="loader-overlay" aria-hidden="true">
        <div class="loader"><span class="ring"></span><span class="txt">Memproses…</span></div>
      </div>
      <div style="font-weight:700;margin-bottom:.25rem">Buat User Hotspot (Test)</div>
      <div class="help">Buat/overwrite user test di router ini.</div>

      <form method="POST" action="{{ route('admin.clients.router.hotspot-test-user',$client) }}" class="form" style="margin-top:.5rem">
        @csrf
        <div class="form-grid form-2">
          <div>
            <label class="label">Mode</label>
            <div class="control">
              <select class="select" name="mode">
                @php $m = $client->auth_mode ?? 'userpass'; @endphp
                <option value="userpass" {{ $m==='userpass'?'selected':'' }}>Username + Password</option>
                <option value="code"     {{ $m==='code'    ?'selected':'' }}>Kode (username = password)</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label">Limit Uptime</label>
            <div class="control">
              <input class="input" name="limit" value="10m" placeholder="10m / 30m / 1h">
            </div>
          </div>

          <div>
            <label class="label">Username (opsional)</label>
            <div class="control"><input class="input mono" name="name" placeholder="auto jika kosong"></div>
          </div>
          <div>
            <label class="label">Password (opsional)</label>
            <div class="control"><input class="input mono" name="password" placeholder="auto jika kosong"></div>
          </div>

          <div>
            <label class="label">Profile</label>
            <div class="control">
              <select class="select" name="profile">
                @foreach($profiles as $p)
                  <option value="{{ $p }}" {{ ($client->default_profile??'default')===$p?'selected':'' }}>{{ $p }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div>
            <label class="label">Server (opsional)</label>
            <div class="control">
              <select class="select" name="server">
                <option value="">— (default) —</option>
                @foreach($servers as $s)
                  <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>

        <div class="row-actions">
          <button class="btn btn--primary" type="submit">Buat User Test</button>
          <a class="btn btn--ghost" href="{{ route('admin.clients.index') }}">Kembali</a>
        </div>
      </form>
    </div>
  </div>

  {{-- Kartu: Test Login Hotspot User (via portal) --}}
  <div class="card tool-card" style="margin-top:12px">
    <div class="loader-overlay" aria-hidden="true">
      <div class="loader"><span class="ring"></span><span class="txt">Memproses…</span></div>
    </div>
    <div style="font-weight:700;margin-bottom:.25rem">Test Login Hotspot (Portal)</div>
    <div class="help">
      Sistem akan mengirim POST ke portal hotspot (server harus bisa menjangkaunya).
      Portal saat ini: <span class="mono">{{ $client->hotspot_portal ?: '—' }}</span>
    </div>

    <form method="POST" action="{{ route('admin.clients.router.hotspot-login-test',$client) }}" class="form" style="margin-top:.5rem">
      @csrf
      <div class="form-grid form-2">
        <div>
          <label class="label">Username</label>
          <div class="control"><input class="input mono" name="username" required></div>
        </div>
        <div>
          <label class="label">Password</label>
          <div class="control"><input class="input mono" name="password" required></div>
        </div>
        <div class="form-2" style="grid-column:1/-1">
          <div>
            <label class="label">Portal URL (opsional)</label>
            <div class="control">
              <input class="input" name="portal" value="{{ $client->hotspot_portal }}" placeholder="http://router/login">
            </div>
            <div class="help">Jika kosong, pakai Portal Domain di client.</div>
          </div>
        </div>
      </div>

      <div class="row-actions">
        <button class="btn">Test Login</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function(){
    document.addEventListener('DOMContentLoaded', function(){
      // Nyalakan overlay loader tiap submit form tools
      document.querySelectorAll('.tool-form').forEach(function(form){
        form.addEventListener('submit', function(e){
          // cari card terdekat
          var card = form.closest('.tool-card');
          if(!card) return;
          var overlay = card.querySelector('.loader-overlay');
          var txt = overlay && overlay.querySelector('.txt');
          if (txt && form.dataset.loading) txt.textContent = form.dataset.loading;

          // tampilkan
          card.classList.add('is-loading');

          // cegah double-click: disable semua tombol submit di form ini
          form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function(btn){
            btn.setAttribute('disabled','disabled');
          });

          // biarkan submit lanjut (halaman akan reload → overlay otomatis hilang)
        }, {capture:true});
      });
    });
  })();
</script>
@endpush
