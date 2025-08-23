@extends('layouts.admin')
@section('title','Clients')

@section('content')
<div class="container">
  <div class="card" style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:12px">
    <div>
      <div style="font-weight:700">Clients</div>
      <div class="help">Kelola router & profil per lokasi</div>
    </div>
    <a href="{{ route('clients.create') }}" class="btn btn--primary">Tambah</a>
  </div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Client ID</th>
          <th>Nama</th>
          <th>Router</th>
          <th>Profile</th>
          <th>Push</th>
          <th>Aktif</th>
          <th class="text-right" style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($clients as $c)
          <tr>
            <td class="mono">{{ $c->client_id }}</td>
            <td>{{ $c->name }}</td>
            <td>
              {{ $c->router_host ?: '—' }}:{{ $c->router_port }}
              <div class="help">User: {{ $c->router_user ?: '—' }}</div>
            </td>
            <td>{{ $c->default_profile }}</td>
            <td>
              @if($c->enable_push) <span class="pill pill--ok">Ya</span>
              @else <span class="pill pill--off">Tidak</span> @endif
            </td>
            <td>
              @if($c->is_active) <span class="pill pill--ok">Aktif</span>
              @else <span class="pill pill--off">Nonaktif</span> @endif
            </td>
            <td style="text-align:right;white-space:nowrap">
              <a href="{{ route('clients.edit',$c) }}" class="btn btn--ghost">Edit</a>
              <form action="{{ route('clients.destroy',$c) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus client ini?')">
                @csrf @method('DELETE')
                <button class="btn">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" style="text-align:center;padding:20px;color:#6b7280">Belum ada client.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
