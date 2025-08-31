@extends('layouts.admin')
@section('title','Dashboard')

@push('head')
<style>
  .kpis{display:grid;gap:12px;grid-template-columns:repeat(4,minmax(0,1fr))}
  .kpi{background:var(--card);border:1px solid var(--bd);border-radius:.75rem;padding:14px}
  .kpi .label{font-size:.82rem;color:var(--mut)}
  .kpi .value{font-weight:800;font-size:1.4rem;margin-top:4px}
  .kpi .sub{font-size:.8rem;color:var(--mut)}

  /* ⬇️ Mini bars: bottom-aligned */
  .mini-bars{
    display:flex;                /* pakai flex agar baseline gampang dikunci di bawah */
    align-items:flex-end;        /* semua batang nempel ke bawah */
    gap:6px;
    height:72px;                 /* tinggi area grafik */
    padding:0 2px;               /* sedikit ruang sisi */
    overflow:hidden;
  }
  .mini-bars > div{
    position:relative;
    flex:1 0 0;                  /* tiap kolom lebar sama, tidak wrap */
    display:flex;
    align-items:flex-end;        /* isi kolom juga nempel bawah */
  }
  .mini-bars .bar{
    width:100%;
    background:#e5edff;
    border-radius:6px;
  }
  .mini-bars .bar.is-sum{
    background:#d1fae5;
    position:absolute;
    left:3px; right:3px; bottom:0; opacity:.9; border-radius:6px;
  }

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

  @if(!empty($isAdmin) && $isAdmin)
    <div class="kpi">
      <div class="label">Clients aktif</div>
      <div class="value">{{ number_format($clientsActive) }}</div>
      <div class="sub"><a href="{{ route('admin.clients.index') }}">Kelola clients →</a></div>
    </div>
  @endif

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

{{-- TOGGLE TABS --}}
<div class="tabs" role="tablist" aria-label="Rentang Waktu">
  <button class="tab-btn" role="tab" aria-selected="true" aria-controls="panel-7d" id="tab-7d">7 Hari</button>
  <button class="tab-btn" role="tab" aria-selected="false" aria-controls="panel-1m" id="tab-1m">1 Bulan</button>
</div>

<div class="tab-panels">
  {{-- PANEL: 7 HARI --}}
  <section id="panel-7d" role="tabpanel" aria-labelledby="tab-7d" class="tab-panel active">
    <div class="grid-2">
      <div class="card">
        <div class="section-title">7 hari terakhir — Volume & Revenue</div>
        @php
          $maxC = max($seriesCount) ?: 1;
          $maxS = max($seriesSum) ?: 1;
        @endphp
        <div class="mini-bars" aria-hidden="true">
          @foreach($seriesCount as $i => $c)
            @php
              $h = max(8, round(($c / $maxC) * 70)); // min 8px
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
          @if(!empty($days))
            <span class="help"> | Rentang: {{ $days[0] }} → {{ $days[count($days)-1] }}</span>
          @endif
          @if(!empty($isAdmin) && $isAdmin)
            <span class="help"> | Basis: Admin fee</span>
          @else
            <span class="help"> | Basis: Net untuk client</span>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="section-title">
          @if(!empty($isAdmin) && $isAdmin)
            Top Clients — 7 hari
          @else
            Ringkasan — 7 hari
          @endif
        </div>
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
          @php
            $from7 = !empty($days) ? ($days[0] ?? null) : null;
            $to7   = !empty($days) ? ($days[count($days)-1] ?? null) : null;
          @endphp

          @if($from7 && $to7)
            <a class="btn btn--ghost"
              href="{{ route('admin.payments.export', ['format'=>'csv','from'=>$from7,'to'=>$to7]) }}">
              Download CSV (7 hari)
            </a>
            <a class="btn btn--primary"
              href="{{ route('admin.payments.export', ['format'=>'xlsx','from'=>$from7,'to'=>$to7]) }}">
              Download XLSX (7 hari)
            </a>
          @else
            <span class="text-sm" style="color:#6b7280">Rentang 7 hari kosong — tidak bisa diexport.</span>
          @endif
        </div>
      </div>
    </div>
  </section>

  {{-- PANEL: 1 BULAN --}}
  <section id="panel-1m" role="tabpanel" aria-labelledby="tab-1m" class="tab-panel">
    <div class="grid-2">
      <div class="card">
        <div class="section-title">1 Bulan Terakhir — Volume & Revenue</div>
        @php
          $maxCM = max($seriesCountM ?? []) ?: 1;
          $maxSM = max($seriesSumM ?? []) ?: 1;
        @endphp
        <div class="mini-bars" aria-hidden="true">
          @foreach(($seriesCountM ?? []) as $i => $c)
            @php
              $h = max(8, round(($c / $maxCM) * 70));
              $hs = max(6, round((($seriesSumM[$i] ?? 0) / $maxSM) * 56));
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
          @if(!empty($monthDays))
            <span class="help"> | Rentang: {{ $monthDays[0] }} → {{ $monthDays[count($monthDays)-1] }}</span>
          @endif
          @if(!empty($isAdmin) && $isAdmin)
            <span class="help"> | Basis: Admin fee</span>
          @else
            <span class="help"> | Basis: Net untuk client</span>
          @endif
          <span class="help"> | Total: Rp{{ number_format((int)($monthRevenue ?? 0),0,',','.') }}</span>
        </div>
      </div>

      <div class="card">
        <div class="section-title">
          @if(!empty($isAdmin) && $isAdmin)
            Top Clients — Bulan Ini
          @else
            Ringkasan — Bulan Ini
          @endif
        </div>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Client</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              @forelse(($topClientsM ?? []) as $c)
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
          @php
            $fromM = !empty($monthDays) ? ($monthDays[0] ?? null) : null;
            $toM   = !empty($monthDays) ? ($monthDays[count($monthDays)-1] ?? null) : null;
          @endphp

          @if($fromM && $toM)
            <a class="btn btn--ghost"
              href="{{ route('admin.payments.export', ['format'=>'csv','from'=>$fromM,'to'=>$toM]) }}">
              Download CSV (1 bulan)
            </a>
            <a class="btn btn--primary"
              href="{{ route('admin.payments.export', ['format'=>'xlsx','from'=>$fromM,'to'=>$toM]) }}">
              Download XLSX (1 bulan)
            </a>
          @else
            <span class="text-sm" style="color:#6b7280">Rentang 1 bulan kosong — tidak bisa diexport.</span>
          @endif
        </div>

      </div>
    </div>
  </section>
</div>

{{-- Pembayaran terbaru --}}
<div class="card" style="margin-top:12px">
  <div class="section-title">Pembayaran terbaru</div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Order</th>
          <th>Client</th>
          <th>Gross</th>
          <th>Admin Fee</th>
          <th>Net (Diterima)</th>
          <th>Status</th>
        </tr>
      </thead>

      <tbody>
      @forelse($recentPayments as $p)
        @php
          $ps   = strtoupper($p->status ?? '');
          // Ambil fee per-client; fallback ke config default jika tidak ada / 0
          $feeDefault = (int) config('pay.admin_fee_flat_default', 0);
          // kalau ada relasi $p->client, pakai nilainya; kalau 0/null, fallback default
          $feeClient  = (int) optional($p->client)->admin_fee_flat ?: 0;
          $adminFee   = $feeClient > 0 ? $feeClient : $feeDefault;
          $gross      = (int) ($p->amount ?? 0);
          $net        = max(0, $gross - $adminFee);
        @endphp
        <tr>
          <td>{{ optional($p->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
          <td class="mono">{{ $p->order_id }}</td>
          <td class="mono">{{ $p->client_id ?: 'DEFAULT' }}</td>
          <td>Rp{{ number_format($gross,0,',','.') }}</td>
          <td class="mono">Rp{{ number_format($adminFee,0,',','.') }}</td>
          <td><strong>Rp{{ number_format($net,0,',','.') }}</strong></td>
          <td>
            @if($ps==='PAID') <span class="pill pill--ok">PAID</span>
            @elseif($ps==='PENDING') <span class="pill">PENDING</span>
            @else <span class="pill pill--off">{{ $ps ?: '—' }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center;padding:16px;color:#6b7280">Belum ada data.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="row-actions" style="margin-top:8px">
    <a class="btn btn--ghost" href="{{ route('admin.payments.index') }}">Lihat semua payments</a>
    <a class="btn btn--ghost" href="{{ route('admin.orders.index') }}">Lihat semua orders</a>
  </div>
</div>

{{-- toggle logic --}}
<script>
  (function(){
    const tabs = document.querySelectorAll('.tab-btn');
    const panels = document.querySelectorAll('.tab-panel');

    function selectTab(id){
      tabs.forEach(btn=>{
        const sel = btn.id === id;
        btn.setAttribute('aria-selected', sel ? 'true' : 'false');
      });
      panels.forEach(p=>{
        p.classList.toggle('active', p.getAttribute('aria-labelledby') === id);
      });
    }

    tabs.forEach(btn=>{
      btn.addEventListener('click', () => selectTab(btn.id));
    });

    // ensure default: 7d
    selectTab('tab-7d');
  })();
</script>
@endsection
