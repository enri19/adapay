@extends('layouts.admin')
@section('title','Payments')

@section('content')
<div class="container">

  <div class="card" style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div>
      <div style="font-weight:700">Payments</div>
      <div class="help">Filter & unduh laporan pembayaran</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <form method="GET" action="{{ route('admin.payments.index') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div class="control">
          <select name="client_id" class="select">
            <option value="">Semua client</option>
            @foreach($clients as $c)
              <option value="{{ $c->client_id }}" {{ $client===$c->client_id?'selected':'' }}>{{ $c->client_id }} — {{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="control">
          <select name="status" class="select">
            <option value="">Semua status</option>
            @foreach(['PENDING','PAID','FAILED','CANCEL'] as $s)
              <option value="{{ $s }}" {{ $status===$s?'selected':'' }}>{{ $s }}</option>
            @endforeach
          </select>
        </div>
        <div class="control"><input class="input" type="date" name="from" value="{{ $from }}"></div>
        <div class="control"><input class="input" type="date" name="to" value="{{ $to }}"></div>
        <div class="control"><input class="input" type="search" name="q" value="{{ $q }}" placeholder="Cari order/ref"></div>
        <button class="btn">Filter</button>
      </form>

      <a class="btn btn--ghost"
         href="{{ route('admin.payments.export', array_merge(request()->all(), ['format'=>'csv'])) }}">
         Download CSV
      </a>
      <a class="btn btn--primary"
         href="{{ route('admin.payments.export', array_merge(request()->all(), ['format'=>'xlsx'])) }}">
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
          <th>Jumlah</th>
          <th>Currency</th>
          <th>Channel</th>
          <th>Status</th>
          <th>Paid At</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $p)
          @php
            $raw = is_array($p->raw ?? null) ? $p->raw : (is_object($p->raw ?? null) ? (array) $p->raw : []);
            $pt  = strtolower($raw['payment_type'] ?? '');
            $channel = '';

            // QRIS terdeteksi dari payment_type atau adanya qr_string
            if (!empty($p->qr_string) || !empty($raw['qr_string']) || $pt === 'qris') {
              $channel = 'QRIS';
            }
            // E-wallet langsung
            elseif (in_array($pt, ['gopay','shopeepay'], true)) {
              $channel = strtoupper($pt);
            }
            // Bank transfer: coba deteksi bank VA
            elseif ($pt === 'bank_transfer') {
              $bank = $raw['va_numbers'][0]['bank'] ?? (isset($raw['permata_va_number']) ? 'permata' : null);
              $channel = $bank ? strtoupper($bank) . ' VA' : 'BANK TRANSFER';
            }
            // Kartu kredit / lainnya
            elseif ($pt) {
              $channel = strtoupper($pt);
            } else {
              // Fallback terakhir (jangan biarkan MIDTRANS menutupi channel)
              // coba infer dari actions
              $act = json_encode($raw['actions'] ?? []);
              if (is_string($act)) {
                if (stripos($act, 'gopay') !== false)      $channel = 'GOPAY';
                elseif (stripos($act, 'shopee') !== false) $channel = 'SHOPEEPAY';
              }
              if (!$channel) $channel = strtoupper($p->provider ?? ''); // contoh: MIDTRANS
            }
          @endphp

          <tr>
            <td>{{ optional($p->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
            <td class="mono">{{ $p->order_id }}</td>
            <td class="mono">{{ $p->client_id ?: 'DEFAULT' }}</td>
            <td>Rp{{ number_format((int)$p->amount,0,',','.') }}</td>
            <td>{{ $p->currency ?: 'IDR' }}</td>
            <td>{{ $channel }}</td>
            <td>
              @if(strtoupper($p->status)==='PAID') <span class="pill pill--ok">PAID</span>
              @elseif(strtoupper($p->status)==='PENDING') <span class="pill">PENDING</span>
              @else <span class="pill pill--off">{{ strtoupper($p->status) }}</span>
              @endif
            </td>
            <td>{{ $p->paid_at ? \Carbon\Carbon::parse($p->paid_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '—' }}</td>
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
