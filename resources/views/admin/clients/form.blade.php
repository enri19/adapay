@extends('layouts.admin')
@section('title', ($client->exists ? 'Edit' : 'Tambah').' Client')

@section('content')
<div class="container">
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
      <h1 style="margin:0;font-size:1.25rem">{{ $client->exists ? 'Edit' : 'Tambah' }} Client</h1>
      <a href="{{ route('admin.clients.index') }}" class="btn btn--ghost">← Kembali</a>
    </div>

    @if ($errors->any())
      <div class="flash flash--err">
        <strong>Periksa lagi:</strong>
        <ul style="margin:.25rem 0 0 1rem">
          @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ $client->exists ? route('admin.clients.update',$client) : route('admin.clients.store') }}" class="form">
      @csrf @if($client->exists) @method('PUT') @endif
      @php $isEdit = $client->exists; @endphp

      <div class="form-grid form-2">
        <div>
          <label class="label">Client ID</label>
          <div class="control"><input class="input mono" name="client_id" value="{{ old('client_id',$client->client_id) }}" placeholder="C1 / DEFAULT" {{ $isEdit?'readonly':'required' }}></div>
          <div class="help">Alfanumerik, unik.</div>
        </div>
        <div>
          <label class="label">Nama</label>
          <div class="control"><input class="input" name="name" value="{{ old('name',$client->name) }}" required></div>
        </div>

        <div>
          <label class="label">Slug</label>
          <div class="control"><input class="input mono" name="slug" value="{{ old('slug',$client->slug) }}" placeholder="C1" {{ $isEdit?'':'required' }}></div>
          <div class="help">Dipakai untuk subdomain/query.</div>
        </div>
        <div>
          <label class="label">Portal Domain (opsional)</label>
          <div class="control"><input class="input" name="portal_domain" value="{{ old('portal_domain',$client->portal_domain) }}" placeholder="hotspot.example.com"></div>
        </div>

        <div>
          <label class="label">Router Host</label>
          <div class="control"><input class="input" name="router_host" value="{{ old('router_host',$client->router_host) }}" placeholder="10.7.0.2 / 192.168.88.1 / IP publik"></div>
        </div>
        <div>
          <label class="label">Router Port</label>
          <div class="control"><input class="input" type="number" name="router_port" value="{{ old('router_port',$client->router_port ?? 8728) }}" min="1" max="65535"></div>
        </div>

        <div>
          <label class="label">Router User</label>
          <div class="control"><input class="input" name="router_user" value="{{ old('router_user',$client->router_user) }}"></div>
        </div>
        <div>
          <label class="label">Router Password</label>
          <div class="control"><input class="input" name="router_pass" value="{{ old('router_pass',$client->router_pass) }}" placeholder="{{ $isEdit ? 'Kosongkan jika tidak diubah' : '' }}"></div>
          <div class="help">Disarankan akses via VPN + API-SSL.</div>
        </div>

        <div>
          <label class="label">Admin Fee</label>
          <div class="control"><input class="input" name="admin_fee_flat" value="{{ old('admin_fee_flat',$client->admin_fee_flat) }}"></div>
        </div>
        
        <div>
        <label class="label">Metode Login</label>
          <div class="control">
            <select class="select" name="auth_mode">
              <option value="userpass" {{ old('auth_mode',$client->auth_mode ?? 'userpass')==='userpass'?'selected':'' }}>Username + Password</option>
              <option value="code"     {{ old('auth_mode',$client->auth_mode ?? 'userpass')==='code'    ?'selected':'' }}>Kode Voucher (1 kolom)</option>
            </select>
          </div>
          <div class="help">Jika "Kode Voucher", user & password di Mikrotik diset sama dengan kode.</div>
        </div>

        <div>
          <label class="label">Hotspot Portal</label>
          <div class="control">
            <input
              class="input"
              type="text"
              id="hotspot_portal"
              name="hotspot_portal"
              value="{{ old('hotspot_portal', $client->hotspot_portal ?? '') }}"
              placeholder="http://url.login.hotspot.kamu"
            />
          </div>
        </div>

        <div>
          <label class="label">Enable Push</label>
          <div class="control">
            <select class="select" name="enable_push">
              <option value="0" {{ old('enable_push',$client->enable_push)? '':'selected' }}>Tidak</option>
              <option value="1" {{ old('enable_push',$client->enable_push)? 'selected':'' }}>Ya</option>
            </select>
          </div>
          <div class="help">Jika Ya, aplikasi akan push user ke router.</div>
        </div>

        <div>
          <label class="label">Default Profile</label>
          <div class="control"><input class="input" name="default_profile" value="{{ old('default_profile',$client->default_profile ?? 'default') }}"></div>
        </div>

        <div>
          <label class="label">Aktif</label>
          <div class="control">
            <select class="select" name="is_active">
              <option value="1" {{ old('is_active',$client->is_active ?? true)? 'selected':'' }}>Ya</option>
              <option value="0" {{ old('is_active',$client->is_active ?? true)? '':'selected' }}>Tidak</option>
            </select>
          </div>
        </div>
      </div>

      <div class="row-actions">
        <button class="btn btn--primary" type="submit">
          <span class="btn__label">Simpan</span>
          <span class="spinner hidden" aria-hidden="true"></span>
        </button>
        <a href="{{ route('admin.clients.index') }}" class="btn btn--ghost">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
