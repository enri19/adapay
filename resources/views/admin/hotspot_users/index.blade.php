@extends('layouts.admin')
@section('title','Hotspot Users')

@section('content')
<div class="container">

  @php
    $statusOptions = ['', 'PENDING', 'PAID', 'FAILED', 'CANCEL', 'EXPIRE'];
  @endphp

  <div class="card" style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div>
      <div style="font-weight:700">Hotspot Users</div>
      <div class="help">Lihat order, status bayar, dan akun hotspot (jika sudah dibuat).</div>
    </div>

    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <form method="GET" action="{{ route('admin.hotspot-users.index') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        @can('is-admin')
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
        @else
          {{-- User non-admin: kunci client_id lewat hidden agar konsisten di pagination/export --}}
          <input type="hidden" name="client_id" value="{{ $client }}">
        @endcan

        <div class="control">
          <select name="status" class="select">
            <option value="">Semua status</option>
            @foreach($statusOptions as $s)
              @if($s!=='')
                <option value="{{ $s }}" {{ ($status ?? '')===$s ? 'selected' : '' }}>{{ $s }}</option>
              @endif
            @endforeach
          </select>
        </div>

        <div class="control"><input class="input" type="date" name="from" value="{{ $from }}"></div>
        <div class="control"><input class="input" type="date" name="to" value="{{ $to }}"></div>
        <div class="control"><input class="input" type="search" name="q" value="{{ $q }}" placeholder="Cari order/name/phone"></div>

        <button class="btn">Filter</button>
      </form>
    </div>
  </div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal Order</th>
          <th>Order ID</th>
          <th>Client</th>
          <th>Pembeli</th>
          <th>Jumlah</th>
          <th>Status Bayar</th>
          <th>Paid At</th>
          <th>Username</th>
          <th>Password</th>
          <th>Profile</th>
          <th>Durasi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          @php
            $payStatus = strtoupper($row->pay_status ?? 'PENDING');
            $amt = $row->amount ? 'Rp' . number_format((int)$row->amount, 0, ',', '.') : '—';
            $cur = $row->currency ?: 'IDR';
            $clientLabel = ($row->client_id ?: 'DEFAULT') . ($row->client_name ? ' — ' . $row->client_name : '');
            $orderAt = $row->order_created_at
              ? \Carbon\Carbon::parse($row->order_created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
              : '—';
            $paidAt = $row->paid_at
              ? \Carbon\Carbon::parse($row->paid_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
              : '—';
          @endphp
          <tr>
            <td>{{ $orderAt }}</td>
            <td class="mono">{{ $row->order_id }}</td>
            <td class="mono">{{ $clientLabel }}</td>
            <td>
              <div>{{ $row->buyer_name ?: '—' }}</div>
              <div class="text-muted" style="font-size:.9em">{{ $row->buyer_phone ?: '' }}</div>
            </td>
            <td>{{ $amt }} <span class="text-muted">{{ $cur }}</span></td>
            <td>
              @if($payStatus==='PAID') <span class="pill pill--ok">PAID</span>
              @elseif($payStatus==='PENDING') <span class="pill">PENDING</span>
              @elseif(in_array($payStatus, ['FAILED','CANCEL','EXPIRE'])) <span class="pill pill--off">{{ $payStatus }}</span>
              @else <span class="pill">{{ $payStatus }}</span>
              @endif
            </td>
            <td>{{ $paidAt }}</td>
            <td class="mono">{{ $row->username ?? '—' }}</td>
            <td class="mono">{{ $row->password ?? '—' }}</td>
            <td class="mono">{{ $row->profile ?? '—' }}</td>
            <td>{{ $row->duration_minutes ? ($row->duration_minutes . ' menit') : '—' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="11" style="text-align:center;padding:20px;color:#6b7280">Belum ada data.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:10px">{{ $rows->links() }}</div>
</div>
@endsection
