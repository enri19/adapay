@extends('layouts.admin')
@section('title','Orders')

@section('content')
<div class="container">

  <div class="card" style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div>
      <div style="font-weight:700">Orders</div>
      <div class="help">Daftar order dan status pembayarannya</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <form method="GET" action="{{ route('admin.orders.index') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div class="control">
          <select name="client_id" class="select">
            <option value="">Semua client</option>
            @foreach($clients as $c)
              <option value="{{ $c->client_id }}" {{ $client===$c->client_id?'selected':'' }}>{{ $c->client_id }} — {{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="control"><input class="input" type="date" name="from" value="{{ $from }}"></div>
        <div class="control"><input class="input" type="date" name="to" value="{{ $to }}"></div>
        <div class="control"><input class="input" type="search" name="q" value="{{ $q }}" placeholder="Cari order/nama/email"></div>
        <button class="btn">Filter</button>
      </form>

      <a class="btn btn--ghost"
         href="{{ route('admin.orders.export', array_merge(request()->all(), ['format'=>'csv'])) }}">
         Download CSV
      </a>
      <a class="btn btn--primary"
         href="{{ route('admin.orders.export', array_merge(request()->all(), ['format'=>'xlsx'])) }}">
         Download XLSX
      </a>
    </div>
  </div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Order ID</th>
          <th>Client</th>
          <th>Voucher</th>
          <th>Buyer</th>
          <th>Jumlah</th>
          <th>Status</th>
          <th>Paid At</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $o)
          <tr>
            <td>{{ optional($o->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
            <td class="mono">{{ $o->order_id }}</td>
            <td class="mono">{{ $o->client_id ?: 'DEFAULT' }}</td>
            <td class="mono">#{{ (int)$o->hotspot_voucher_id }}</td>
            <td>
              {{ $o->buyer_name ?: '—' }}
              <div class="help">{{ $o->buyer_email ?: '' }} {{ $o->buyer_phone ? ' · '.$o->buyer_phone : '' }}</div>
            </td>
            <td>{{ $o->amount ? 'Rp'.number_format((int)$o->amount,0,',','.') : '—' }}</td>
            <td>
              @php $ps = strtoupper($o->payment_status ?: 'PENDING'); @endphp
              @if($ps==='PAID') <span class="pill pill--ok">PAID</span>
              @elseif($ps==='PENDING') <span class="pill">PENDING</span>
              @else <span class="pill pill--off">{{ $ps }}</span>
              @endif
            </td>
            <td>{{ $o->paid_at ? \Carbon\Carbon::parse($o->paid_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="8" style="text-align:center;padding:20px;color:#6b7280">Belum ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:10px">{{ $rows->links() }}</div>
</div>
@endsection
