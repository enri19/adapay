@extends('layouts.admin')
@section('title', ($user->exists ? 'Edit' : 'Tambah').' User')

@section('content')
<div class="container">
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
      <h1 style="margin:0;font-size:1.25rem">{{ $user->exists ? 'Edit' : 'Tambah' }} User</h1>
      <a href="{{ route('admin.users.index') }}" class="btn btn--ghost">← Kembali</a>
    </div>

    @if ($errors->any())
      <div class="flash flash--err">
        <strong>Periksa lagi:</strong>
        <ul style="margin:.25rem 0 0 1rem">
          @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif
    @if(session('error'))
      <div class="flash flash--err">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ $user->exists ? route('admin.users.update',$user) : route('admin.users.store') }}" class="form">
      @csrf
      @if($user->exists) @method('PUT') @endif
      @php $isEdit = $user->exists; @endphp

      <div class="form-grid form-2">
        <div>
          <label class="label">Nama</label>
          <div class="control"><input class="input" name="name" value="{{ old('name',$user->name) }}" required></div>
        </div>
        <div>
          <label class="label">Email</label>
          <div class="control"><input class="input mono" type="email" name="email" value="{{ old('email',$user->email) }}" required></div>
        </div>

        <div>
          <label class="label">Role</label>
          <div class="control">
            <select class="select" name="role" required>
              @foreach(['admin','user'] as $r)
                <option value="{{ $r }}" {{ old('role',$user->role ?? 'user')===$r?'selected':'' }}>
                  {{ strtoupper($r) }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="help">Admin tidak wajib terikat ke client.</div>
        </div>

        <div>
          <label class="label">Client (opsional)</label>
          <div class="control">
            <select class="select" name="client_id">
              <option value="">— Tidak terkait —</option>
              @foreach($clients as $c)
                <option value="{{ $c->client_id }}" {{ old('client_id',$user->client_id) === $c->client_id ? 'selected':'' }}>
                  {{ $c->client_id }} — {{ $c->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="help">User biasa sebaiknya terikat ke client untuk pembatasan data.</div>
        </div>

        <div>
          <label class="label">Password {{ $isEdit ? '(opsional)' : '' }}</label>
          <div class="control"><input class="input" type="password" name="password" {{ $isEdit ? '' : 'required' }} placeholder="{{ $isEdit ? 'Kosongkan jika tidak diubah' : '' }}"></div>
        </div>
        <div>
          <label class="label">Konfirmasi Password {{ $isEdit ? '(opsional)' : '' }}</label>
          <div class="control"><input class="input" type="password" name="password_confirmation" {{ $isEdit ? '' : 'required' }}></div>
        </div>
      </div>

      <div class="row-actions">
        <button class="btn btn--primary" type="submit">
          <span class="btn__label">Simpan</span>
          <span class="spinner hidden" aria-hidden="true"></span>
        </button>
        <a href="{{ route('admin.users.index') }}" class="btn btn--ghost">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
