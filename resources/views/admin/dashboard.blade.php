@extends('layouts.admin')
@section('title','Dashboard')

@push('head')
<style>
  .kpis{display:grid;gap:12px;grid-template-columns:repeat(4,minmax(0,1fr))}
  .kpi{background:var(--card);border:1px solid var(--bd);border-radius:.75rem;padding:14px}
  .kpi .label{font-size:.82rem;color:var(--mut)}
  .kpi .value{font-weight:800;font-size:1.4rem;margin-top:4px}
  .kpi .sub{font-size:.8rem;color:var(--mut)}
  .mini-bars{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;gap:6px;align-items:end;height:72px}
  .mini-bars .bar{background:#e5edff;border-radius:6px}
  .mini-bars .bar.is-sum{background:#d1fae5}
  .section-title{font-weight:700;margin-bottom:8px}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
  @media(max-width:900px){ .kpis{grid-template-columns:1fr 1fr} }
</style>
@endpush

@section('content')
<div class="kpis">
  <div class="kpi">
    <div class="label">Login sebagai</div>
    <div class="value" title="{{ auth()->user()->email }}">{{ auth()->user()->name }}</div>
    <div class="sub">Selamat datang kembali 👋</div>
  </div>
  <div class="kpi">
    <div class="label">Clients aktif</div>
    <div class="value">{{ number_format($clientsActive) }}</div>
    <div class="sub"><a href="{{ route('clients.index') }}">Kelola clients →</a></div>
  </div>
  <div class="kpi">
    <div class="label">Payments (24 jam)</div>
    <div class="value">{{ number_format($payments24h) }}</div>
    <div class="sub"><a href="{{ route('admin.payments.index') }}?from={{ now()->subDay()->toDateString() }}">Lihat detail →</a></div>
  </div>
  <div class="kpi">
    <div class="label">Revenue hari ini</div>
    <div class="value">Rp{{ number_format($todayRevenue,0,',','.') }}</div>
    <div class="sub">Pending: {{ number_format($pendingCount) }}</div>
  </div>
</div>

<div class="grid gap-3 md:grid-cols-2" style="margin-top:12px">
  <div class="card">
    <div class="section-title">7 hari terakhir — Volume & Revenue</div>
    @php
      $maxC = max($seriesCount) ?: 1;
      $maxS = max($seriesSum) ?: 1;
    @endphp
    <div class="mini-bars" aria-hidden="true">
      @foreach($seriesCount as $i => $c)
        @php
          $h = max(8, round(($c / $maxC) * 70)); // tinggi minimal 8px
          $hs = max(6, round((($seriesSum[$i] ?? 0) / $maxS) * 56)); // overlay revenue
        @endphp
        <div style="position:relative">
          <div class="bar" style="height:{{ $h }}px"></div>
          <div class="bar is-sum" style="height:{{ $hs }}px;position:absolute;left:3px;right:3px;bottom:0;opacity:.9"></div>
        </div>
      @endforeach
    </div>
    <div class="help" style="margin-top:6px">
      <span class="pill" style="border-color:#c7d2fe;background:#eef2ff">Volume</span>
      <span class="pill" style="border-color:#a7f3d0;background:#ecfdf5">Revenue</span>
      <span class="help"> | Rentang: {{ $days[0] }} → {{ $days[count($days)-1] }}</span>
    </div>
  </div>

  <div class="card">
    <div class="section-title">Top Clients — 7 hari</div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Client</th>
            <th>Revenue</th>
          </tr>
        </thead>
        <tbody>
          @forelse($topClients7 as $c)
            <tr>
              <td class="mono">{{ $c->client_id ?: 'DEFAULT' }}</td>
              <td>Rp{{ number_format((int)$c->total,0,',','.') }}</td>
            </tr>
          @empty
            <tr><td colspan="2" style="text-align:center;padding:16px;color:#6b7280">Belum ada transaksi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="row-actions" style="margin-top:8px">
      <a class="btn btn--ghost" href="{{ route('admin.payments.export',['format'=>'csv']) }}">Download CSV</a>
      <a class="btn btn--primary" href="{{ route('admin.payments.export',['format'=>'xlsx']) }}">Download XLSX</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <div class="section-title">Pembayaran terbaru</div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Order</th>
          <th>Client</th>
          <th>Jumlah</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($recentPayments as $p)
          @php $ps = strtoupper($p->status ?: ''); @endphp
          <tr>
            <td>{{ optional($p->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
            <td class="mono">{{ $p->order_id }}</td>
            <td class="mono">{{ $p->client_id ?: 'DEFAULT' }}</td>
            <td>Rp{{ number_format((int)$p->amount,0,',','.') }}</td>
            <td>
              @if($ps==='PAID') <span class="pill pill--ok">PAID</span>
              @elseif($ps==='PENDING') <span class="pill">PENDING</span>
              @else <span class="pill pill--off">{{ $ps ?: '—' }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align:center;padding:16px;color:#6b7280">Belum ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="row-actions" style="margin-top:8px">
    <a class="btn btn--ghost" href="{{ route('admin.payments.index') }}">Lihat semua payments</a>
    <a class="btn btn--ghost" href="{{ route('admin.orders.index') }}">Lihat semua orders</a>
  </div>
</div>
@endsection
