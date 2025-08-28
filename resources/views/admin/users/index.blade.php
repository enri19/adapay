@extends('layouts.admin')
@section('title','Users')

@section('content')
<div class="container">
  {{-- Header --}}
  <div class="card" style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:12px">
    <div>
      <div style="font-weight:700">Users</div>
      <div class="help">Kelola akun, role, dan keterikatan client</div>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn btn--primary">Tambah</a>
  </div>

  {{-- Filter (opsional, rapi ala Payments) --}}
  <div class="card" style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <form method="GET" action="{{ route('admin.users.index') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <div class="control">
        <select name="role" class="select">
          <option value="">Semua role</option>
          @foreach(['admin','user'] as $r)
            <option value="{{ $r }}" {{ ($role ?? '')===$r?'selected':'' }}>{{ strtoupper($r) }}</option>
          @endforeach
        </select>
      </div>
      <div class="control">
        <select name="client_id" class="select">
          <option value="">Semua client</option>
          @foreach($clients as $c)
            <option value="{{ $c->client_id }}" {{ ($client ?? '')===$c->client_id?'selected':'' }}>
              {{ $c->client_id }} — {{ $c->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="control">
        <input class="input" type="search" name="q" value="{{ $q ?? '' }}" placeholder="Cari nama/email">
      </div>
      <button class="btn">Filter</button>
    </form>
  </div>

  {{-- Flash messages --}}
  @if(session('error'))
    <div class="flash flash--err"><strong>Error:</strong> {{ session('error') }}</div>
  @endif
  @if(session('ok'))
    <div class="flash flash--ok">{{ session('ok') }}</div>
  @endif

  {{-- Tabel --}}
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Email</th>
          <th>Role</th>
          <th>Client</th>
          <th class="text-right" style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $u)
          <tr>
            <td>{{ $u->name }}</td>
            <td class="mono">{{ $u->email }}</td>
            <td>
              <span class="pill">{{ strtoupper($u->role ?? 'user') }}</span>
            </td>
            <td class="mono">{{ $u->client_id ?: '—' }}</td>
            <td style="text-align:right;white-space:nowrap">
              <a href="{{ route('admin.users.edit',$u) }}" class="btn btn--ghost">Edit</a>
              <form action="{{ route('admin.users.destroy',$u) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus user ini?')">
                @csrf @method('DELETE')
                <button class="btn">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align:center;padding:20px;color:#6b7280">Belum ada user.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:10px">{{ $rows->links() }}</div>
</div>
@endsection
