@extends('layouts.admin')
@section('title','Router & Hotspot Tools')

@section('content')
<div class="container">
  <div class="card" style="margin-bottom:12px">
    <div style="font-weight:700">Router & Hotspot Tools</div>
    <div class="help">Client: <span class="mono">{{ $client->client_id }}</span> — {{ $client->name }}</div>
  </div>

  @if(session('ok'))    <div class="flash flash--ok">{{ session('ok') }}</div> @endif
  @if(session('error')) <div class="flash flash--err">{{ session('error') }}</div> @endif

  <div class="form-grid form-2">
    {{-- Kartu: Test Koneksi Router --}}
    <div class="card">
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
    <div class="card">
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
  <div class="card" style="margin-top:12px">
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
