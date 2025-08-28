@extends('layouts.admin')
@section('title','Vouchers')

@section('content')
<div class="container">
  <div class="card" style="margin-bottom:12px">
    <div style="display:flex;gap:12px;align-items:end;justify-content:space-between;flex-wrap:wrap">
      <div>
        <div style="font-weight:700">Vouchers</div>
        <div class="help">Kelola daftar voucher per client</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <form method="GET" action="{{ route('admin.vouchers.index') }}" style="display:flex;gap:8px;align-items:center">
          <div class="control">
            <select name="client_id" class="select">
              <option value="">Semua client</option>
              @foreach($clients as $c)
                <option value="{{ $c->client_id }}" {{ $client===$c->client_id?'selected':'' }}>
                  {{ $c->client_id }} — {{ $c->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="control"><input class="input" type="search" name="q" value="{{ $q }}" placeholder="Cari nama/kode/profil"></div>
          <button class="btn">Filter</button>
        </form>
        <a href="{{ route('admin.vouchers.create') }}" class="btn btn--primary">Tambah</a>
      </div>
    </div>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Client</th>
          <th>Nama</th>
          <th>Harga</th>
          <th>Durasi</th>
          <th>Profile</th>
          <th>Kode</th>
          <th>Aktif</th>
          <th style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $v)
          <tr>
            <td class="mono">{{ $v->client_id ?: 'DEFAULT' }}</td>
            <td>{{ $v->name }}</td>
            <td>Rp{{ number_format((int)$v->price,0,',','.') }}</td>
            <td>{{ (int)$v->duration_minutes }} mnt</td>
            <td class="mono">{{ $v->profile }}</td>
            <td class="mono">{{ $v->code ?: '—' }}</td>
            <td>
              @if($v->is_active) <span class="pill pill--ok">Ya</span>
              @else <span class="pill pill--off">Tidak</span> @endif
            </td>
            <td style="text-align:right;white-space:nowrap">
              <a href="{{ route('admin.vouchers.edit',$v) }}" class="btn btn--ghost">Edit</a>
              <form action="{{ route('admin.vouchers.destroy',$v) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus voucher ini?')">
                @csrf @method('DELETE')
                <button class="btn">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" style="text-align:center;padding:20px;color:#6b7280">Belum ada voucher.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:10px">{{ $rows->links() }}</div>
</div>
@endsection
