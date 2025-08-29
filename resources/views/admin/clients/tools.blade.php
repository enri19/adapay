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
    <div class="help">
      Client: <span class="mono">{{ $client->client_id }}</span> — {{ $client->name }}
      <span class="help"> • API: {{ $client->router_host ?: '—' }}:{{ $client->router_port ?: 8728 }}</span>
      @isset($online)
        @if($online)
          <span class="pill pill--ok" style="margin-left:.5rem">Router Online</span>
        @else
          <span class="pill pill--off" style="margin-left:.5rem">Router Offline (pakai input manual)</span>
        @endif
      @endisset
    </div>
  </div>

  <div class="form-grid form-2">
    {{-- Kartu: Test Koneksi Router --}}
    <div class="card tool-card">
      <div class="loader-overlay" aria-hidden="true">
        <div class="loader"><span class="ring"></span><span class="txt">Memproses…</span></div>
      </div>
      <div style="font-weight:700;margin-bottom:.25rem">Test Koneksi Router</div>
      <div class="help">{{ $client->router_host ?: '—' }}:{{ $client->router_port ?: 8728 }} — user: {{ $client->router_user ?: '—' }}</div>

      {{-- Port-only --}}
      <form method="POST"
            action="{{ route('admin.clients.router.test',$client) }}"
            class="tool-form"
            data-loading="Menghubungkan router…"
            style="margin-top:.75rem">
        @csrf
        <button class="btn btn--primary">Tes Port</button>
      </form>

      {{-- Tes + Auth (tanpa hidden) --}}
      <form method="POST"
            action="{{ route('admin.clients.router.test',$client) }}"
            class="tool-form"
            data-loading="Tes + autentikasi…"
            data-deep="1"
            style="margin-top:.5rem">
        @csrf
        <button class="btn">Tes + Auth</button>
      </form>
    </div>

    {{-- Kartu: Buat User Hotspot (Test) --}}
    <div class="card tool-card">
      <div class="loader-overlay" aria-hidden="true">
        <div class="loader"><span class="ring"></span><span class="txt">Memproses…</span></div>
      </div>
      <div style="font-weight:700;margin-bottom:.25rem">Buat User Hotspot (Test)</div>
      <div class="help">Buat/overwrite user test di router ini.</div>

      <form method="POST"
            action="{{ route('admin.clients.router.hotspot-test-user',$client) }}"
            class="form tool-form"
            data-loading="Membuat user hotspot…"
            style="margin-top:.5rem">
        @csrf
        <div class="form-grid form-2">
          <div>
            <label class="label">Mode</label>
            <div class="control">
              @php $m = $client->auth_mode ?? 'code'; @endphp
              <select class="select" name="mode">
                <option value="userpass" {{ $m==='userpass'?'selected':'' }}>Username + Password</option>
                <option value="code"     {{ $m==='code'    ?'selected':'' }}>Kode (username = password)</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label">Limit Uptime</label>
            <div class="control"><input class="input" name="limit" value="10m" placeholder="10m / 30m / 1h"></div>
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

    {{-- Kartu: Test Login Hotspot (API) --}}
    <div class="card tool-card" style="margin-top:12px">
      <div class="loader-overlay" aria-hidden="true">
        <div class="loader"><span class="ring"></span><span class="txt">Memproses…</span></div>
      </div>
      <div style="font-weight:700;margin-bottom:.25rem">Test Login Hotspot (API)</div>
      <div class="help">Server akan meminta router membuat session aktif untuk perangkat yang terdeteksi (tanpa portal lokal).</div>

      <form method="POST"
            action="{{ route('admin.clients.router.hotspot-login-test',$client) }}"
            class="form tool-form"
            data-loading="Menguji login hotspot…"
            style="margin-top:.5rem">
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
          <div>
            <label class="label">MAC klien (opsional)</label>
            <div class="control"><input class="input mono" name="mac" placeholder="AA:BB:CC:DD:EE:FF"></div>
            <div class="help">Kalau diisi, IP klien dicari berdasarkan MAC.</div>
          </div>
        </div>

        <div class="row-actions">
          <button class="btn">Test Login</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  function flash(type, text){
    var box = document.createElement('div');
    box.className = 'flash ' + (type==='ok' ? 'flash--ok' : 'flash--err');
    box.textContent = text;
    var main = document.querySelector('.main');
    main && main.insertBefore(box, main.firstChild);
    setTimeout(()=>{ box.remove(); }, 6000);
  }

  function submitToolForm(form){
    const card = form.closest('.tool-card');
    if (!card) return;

    const overlay = card.querySelector('.loader-overlay');
    const txt = overlay && overlay.querySelector('.txt');
    if (txt && form.dataset.loading) txt.textContent = form.dataset.loading;
    card.classList.add('is-loading');

    const btns = form.querySelectorAll('button[type="submit"],input[type="submit"]');
    btns.forEach(b=>b.setAttribute('disabled','disabled'));

    const fd = new FormData(form);
    if (form.dataset.deep === '1') fd.set('deep','1');

    const csrf = form.querySelector('input[name="_token"]')?.value || '';

    fetch(form.action, {
      method: form.method || 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json'
      },
      body: fd,
      credentials: 'same-origin'
    })
    .then(async res => {
      let json = null;
      try { json = await res.json(); } catch(e){}
      const ok  = res.ok && json && json.ok !== false;
      const msg = (json && json.message) ? json.message : (ok ? 'Berhasil.' : 'Terjadi kesalahan.');
      flash(ok ? 'ok' : 'err', msg);
    })
    .catch(err => {
      flash('err', 'Gagal mengirim: ' + (err?.message || err));
    })
    .finally(() => {
      card.classList.remove('is-loading');
      btns.forEach(b=>b.removeAttribute('disabled'));
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.tool-form').forEach(function(form){
      form.addEventListener('submit', function(e){
        e.preventDefault();
        submitToolForm(form);
      }, {capture:true});
    });
  });
})();
</script>
@endpush
