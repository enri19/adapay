@extends('layouts.admin')
@section('title', ($voucher->exists ? 'Edit' : 'Tambah').' Voucher')

@section('content')
<div class="container">
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
      <h1 style="margin:0;font-size:1.25rem">{{ $voucher->exists ? 'Edit' : 'Tambah' }} Voucher</h1>
      <a href="{{ route('vouchers.index') }}" class="btn btn--ghost">← Kembali</a>
    </div>

    @if ($errors->any())
      <div class="flash flash--err">
        <strong>Periksa lagi:</strong>
        <ul style="margin:.25rem 0 0 1rem">
          @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ $voucher->exists ? route('vouchers.update',$voucher) : route('vouchers.store') }}" class="form">
      @csrf @if($voucher->exists) @method('PUT') @endif

      <div class="form-grid form-2">
        <div>
          <label class="label">Client</label>
          <div class="control">
            <select name="client_id" class="select" required>
              @foreach($clients as $c)
                <option value="{{ $c->client_id }}" {{ old('client_id',$voucher->client_id ?: 'DEFAULT')===$c->client_id?'selected':'' }}>
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
          <label class="label">Profile Mikrotik</label>
          <div class="control"><input class="input" name="profile" value="{{ old('profile',$voucher->profile ?: 'default') }}" required></div>
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
        <a href="{{ route('vouchers.index') }}" class="btn btn--ghost">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
