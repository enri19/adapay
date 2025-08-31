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
          @if(!empty($isAdmin) && $isAdmin)
            <th>Gross</th>
            <th>Admin Fee</th>
            <th>Net</th>
          @else
            <th>Gross</th>
            <th>Admin Fee</th>
            <th>Net (Diterima)</th>
          @endif
          <th>Currency</th>
          <th>Channel</th>
          <th>Status</th>
          <th>Paid At</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $p)
          @php
            // Channel detection (tetap seperti punyamu)
            $raw = is_array($p->raw ?? null) ? $p->raw : (is_object($p->raw ?? null) ? (array) $p->raw : []);
            $pt  = strtolower($raw['payment_type'] ?? '');
            $channel = '';

            if (!empty($p->qr_string) || !empty($raw['qr_string']) || $pt === 'qris') {
              $channel = 'QRIS';
            } elseif (in_array($pt, ['gopay','shopeepay'], true)) {
              $channel = strtoupper($pt);
            } elseif ($pt === 'bank_transfer') {
              $bank = $raw['va_numbers'][0]['bank'] ?? (isset($raw['permata_va_number']) ? 'permata' : null);
              $channel = $bank ? strtoupper($bank) . ' VA' : 'BANK TRANSFER';
            } elseif ($pt) {
              $channel = strtoupper($pt);
            } else {
              $act = json_encode($raw['actions'] ?? []);
              if (is_string($act)) {
                if (stripos($act, 'gopay') !== false)      $channel = 'GOPAY';
                elseif (stripos($act, 'shopee') !== false) $channel = 'SHOPEEPAY';
              }
              if (!$channel) $channel = strtoupper($p->provider ?? '');
            }

            // ===== Fee logic (fallback ke config default jika DB kosong/0) =====
            $feeDefault = (int) config('pay.admin_fee_flat_default', 0);

            // Controller sudah select:
            //   gross: payments.amount
            //   admin_fee: COALESCE(NULLIF(clients.admin_fee_flat,0), $feeDefault)
            //   net: GREATEST(amount - fee, 0)
            // Tapi tetap guard di view bila belum update controllernya:
            $gross     = (int) ($p->gross   ?? $p->amount   ?? 0);
            $adminFee  = (int) ($p->admin_fee ?? 0);
            if ($adminFee <= 0) {
              // kalau dari controller belum ada/0, fallback manual
              $adminFee = $feeDefault;
              // kalau ada relasi client dengan kolom admin_fee_flat > 0, pakai itu
              if (isset($p->client) && is_numeric($p->client->admin_fee_flat ?? null) && (int)$p->client->admin_fee_flat > 0) {
                $adminFee = (int) $p->client->admin_fee_flat;
              }
            }
            $net = max(0, $gross - $adminFee);
          @endphp

          <tr>
            <td>{{ optional($p->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
            <td class="mono">{{ $p->order_id }}</td>
            <td class="mono">{{ $p->client_id ?: 'DEFAULT' }}</td>

            <td>Rp{{ number_format($gross,0,',','.') }}</td>
            <td class="mono">Rp{{ number_format($adminFee,0,',','.') }}</td>
            <td><strong>Rp{{ number_format($net,0,',','.') }}</strong></td>

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
          {{-- jumlah kolom: 3 (tanggal, order, client) + 3 (gross, fee, net) + 4 (currency, channel, status, paid) = 10 --}}
          <tr><td colspan="10" style="text-align:center;padding:20px;color:#6b7280">Belum ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:10px">{{ $rows->links() }}</div>
</div>
@endsection
