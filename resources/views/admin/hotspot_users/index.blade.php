@extends('layouts.admin')
@section('title','Hotspot Users')

@section('content')
<div class="container">

  <div class="card" style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div>
      <div style="font-weight:700">Hotspot Users</div>
      <div class="help">Daftar akun hotspot yang dibuat dari order.</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <form method="GET" action="{{ route('admin.hotspot-users.index') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div class="control">
          <select name="client_id" class="select">
            <option value="">Semua client</option>
            @foreach($clients as $c)
              <option value="{{ $c->client_id }}" {{ ($client===$c->client_id)?'selected':'' }}>
                {{ $c->client_id }} — {{ $c->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="control">
          <select name="status" class="select">
            <option value="">Semua status</option>
            <option value="READY" {{ $status==='READY'?'selected':'' }}>READY</option>
            <option value="PENDING" {{ $status==='PENDING'?'selected':'' }}>PENDING</option>
          </select>
        </div>

        <div class="control"><input class="input" type="date" name="from" value="{{ $from }}"></div>
        <div class="control"><input class="input" type="date" name="to" value="{{ $to }}"></div>
        <div class="control"><input class="input" type="search" name="q" value="{{ $q }}" placeholder="Cari order/username"></div>
        <button class="btn">Filter</button>
      </form>
    </div>
  </div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Dibuat</th>
          <th>Order ID</th>
          <th>Client</th>
          <th>Username</th>
          <th>Password</th>
          <th>Profile</th>
          <th>Durasi</th>
          <th>Status</th>
          <th>Provisioned At</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $u)
          @php
            $isReady = !empty($u->provisioned_at);
            $clientId = $u->client_id ?: 'DEFAULT';
          @endphp
          <tr>
            <td>{{ optional($u->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
            <td class="mono">{{ $u->order_id }}</td>
            <td class="mono">{{ $clientId }}</td>
            <td class="mono">{{ $u->username }}</td>
            <td class="mono">
              <span class="pwd" data-pwd="{{ $u->password }}">••••••</span>
              <button class="btn btn--xs btn--ghost toggle-pwd" type="button" aria-label="Toggle password">lihat</button>
            </td>
            <td>{{ $u->profile ?: '—' }}</td>
            <td>{{ (int) $u->duration_minutes ?: 0 }} menit</td>
            <td>
              @if($isReady) <span class="pill pill--ok">READY</span>
              @else <span class="pill">PENDING</span>
              @endif
            </td>
            <td>{{ $u->provisioned_at ? \Carbon\Carbon::parse($u->provisioned_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="9" style="text-align:center;padding:20px;color:#6b7280">Belum ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:10px">{{ $rows->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function(e){
  const btn = e.target.closest('.toggle-pwd');
  if (!btn) return;
  const td = btn.closest('td');
  const span = td.querySelector('.pwd');
  const shown = span && span.dataset && span.dataset.shown === '1';
  if (span) {
    if (shown) {
      span.textContent = '••••••';
      span.dataset.shown = '0';
      btn.textContent = 'lihat';
    } else {
      span.textContent = span.dataset.pwd || '';
      span.dataset.shown = '1';
      btn.textContent = 'sembunyikan';
    }
  }
});
</script>
@endpush
