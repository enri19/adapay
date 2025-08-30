@extends('layouts.admin')
@section('title', ($voucher->exists ? 'Edit' : 'Tambah').' Voucher')

@section('content')
<div class="container">
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
      <h1 style="margin:0;font-size:1.25rem">{{ $voucher->exists ? 'Edit' : 'Tambah' }} Voucher</h1>
      <a href="{{ route('admin.vouchers.index', ['client_id' => $clientId ?? null]) }}" class="btn btn--ghost">← Kembali</a>
    </div>

    @if ($errors->any())
      <div class="flash flash--err">
        <strong>Periksa lagi:</strong>
        <ul style="margin:.25rem 0 0 1rem">
          @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ $voucher->exists ? route('admin.vouchers.update',$voucher) : route('admin.vouchers.store') }}" class="form">
      @csrf @if($voucher->exists) @method('PUT') @endif

      @php
        $routeReload = $voucher->exists ? route('admin.vouchers.edit',$voucher) : route('admin.vouchers.create');
        $selClientId = old('client_id', $clientId ?? ($voucher->client_id ?: 'DEFAULT'));
      @endphp

      <div class="form-grid form-2">
        <div>
          <label class="label">Client</label>
          <div class="control">
            <select name="client_id" class="select" required
              onchange="(function(s){var u=new URL('{{ $routeReload }}',window.location);u.searchParams.set('client_id',s.value);window.location=u.toString();})(this)">
              @foreach($clients as $c)
                <option value="{{ $c->client_id }}" {{ $selClientId===$c->client_id?'selected':'' }}>
                  {{ $c->client_id }} — {{ $c->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="help">Gunakan <code>DEFAULT</code> untuk global (tampil di semua lokasi).</div>
        </div>

        <div>
          <label class="label">Nama Voucher</label>
          <div class="control"><input class="input" name="name" value="{{ old('name',$voucher->name) }}" required></div>
        </div>

        <div>
          <label class="label">Harga (Rp)</label>
          <div class="control"><input class="input" name="price" value="{{ old('price',$voucher->price) }}" placeholder="contoh: 10.000" required></div>
          <div class="help">Boleh pakai titik/koma, akan diparse ke angka.</div>
        </div>

        <div>
          <label class="label">Durasi (menit)</label>
          <div class="control"><input class="input" type="number" min="1" name="duration_minutes" value="{{ old('duration_minutes',$voucher->duration_minutes) }}" required></div>
        </div>

        <div>
          <label class="label">
            Profile Mikrotik
            @if(!empty($profiles))
              <span class="pill pill--ok" style="margin-left:.25rem">auto-load</span>
            @elseif(isset($online) && !$online)
              <span class="pill pill--off" style="margin-left:.25rem">offline</span>
            @endif
          </label>

          @if(!empty($profiles))
            <div class="control">
              <select class="select" name="profile" required>
                @php $cur = old('profile', $voucher->profile ?: 'default'); @endphp
                @foreach($profiles as $p)
                  <option value="{{ $p }}" {{ $cur===$p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
              </select>
            </div>
            <div class="help">
              {{ $online ? 'Profil dimuat dari Mikrotik (' . ($servers ? implode(', ', (array)$servers) : 'server tidak terdeteksi') . ')' : 'Tidak terhubung ke Mikrotik' }}.
            </div>
          @else
            <div class="control"><input class="input" name="profile" value="{{ old('profile',$voucher->profile ?: 'default') }}" required></div>
            <div class="help">Gagal memuat dari Mikrotik, isi manual.</div>
          @endif
        </div>

        <div>
          <label class="label">Kode (opsional)</label>
          <div class="control"><input class="input" name="code" value="{{ old('code',$voucher->code) }}" placeholder="mis. VCR-1JAM"></div>
        </div>

        <div>
          <label class="label">Aktif</label>
          <div class="control">
            <select class="select" name="is_active">
              <option value="1" {{ old('is_active', $voucher->is_active ? 1 : 0) ? 'selected' : '' }}>Ya</option>
              <option value="0" {{ old('is_active', $voucher->is_active ? 1 : 0) ? '' : 'selected' }}>Tidak</option>
            </select>
          </div>
        </div>
      </div>

      <div class="row-actions">
        <button class="btn btn--primary" type="submit">
          <span class="btn__label">Simpan</span>
          <span class="spinner hidden" aria-hidden="true"></span>
        </button>
        <a href="{{ route('admin.vouchers.index', ['client_id' => $selClientId]) }}" class="btn btn--ghost">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
