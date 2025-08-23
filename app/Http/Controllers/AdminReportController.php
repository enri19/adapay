<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\Payment;
use App\Models\HotspotOrder;
use Carbon\Carbon;

class AdminReportController extends Controller
{
  public function paymentsExport(Request $r)
  {
    $fmt   = strtolower($r->query('format','csv')) === 'xlsx' ? 'xlsx' : 'csv';
    $cid   = strtoupper(trim((string) $r->query('client_id','')));
    $st    = strtoupper(trim((string) $r->query('status',''))); // PENDING/PAID/...
    $from  = $this->parseDate($r->query('from')); // Y-m-d
    $to    = $this->parseDate($r->query('to'));   // Y-m-d (inclusive)
    $tz    = config('app.timezone','Asia/Jakarta');

    $q = Payment::query()
      ->when($cid !== '', fn($qq) => $qq->where('client_id',$cid))
      ->when($st  !== '', fn($qq) => $qq->where('status',$st))
      ->when($from, fn($qq) => $qq->whereDate('created_at','>=',$from))
      ->when($to,   fn($qq) => $qq->whereDate('created_at','<=',$to))
      ->orderBy('created_at');

    $filename = 'payments_'.$this->rangeSlug($from,$to).'_'.now()->format('Ymd_His').'.'.($fmt==='xlsx'?'xlsx':'csv');

    // Jika paket Excel ada & format xlsx diminta => unduh XLSX
    if ($fmt === 'xlsx' && class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
      return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\PaymentsExport($q, $tz),
        $filename
      );
    }

    // Default: stream CSV
    $headers  = ['Order ID','Client','Provider','Channel/Ref','Amount','Currency','Status','Paid At','Created At'];
    $mapper   = function ($p) use ($tz) {
      $paidAt = $p->paid_at ? Carbon::parse($p->paid_at)->timezone($tz)->format('Y-m-d H:i:s') : '';
      $crtAt  = $p->created_at ? Carbon::parse($p->created_at)->timezone($tz)->format('Y-m-d H:i:s') : '';
      $channel= $p->provider_ref ?: ($p->raw['payment_type'] ?? '');
      return [
        $p->order_id,
        $p->client_id ?: 'DEFAULT',
        $p->provider ?: 'midtrans',
        $channel,
        (int)$p->amount,
        $p->currency ?: 'IDR',
        strtoupper($p->status ?: ''),
        $paidAt,
        $crtAt,
      ];
    };

    return $this->streamCsv($q, $headers, $mapper, $filename);
  }

  public function ordersExport(Request $r)
  {
    $fmt   = strtolower($r->query('format','csv')) === 'xlsx' ? 'xlsx' : 'csv';
    $cid   = strtoupper(trim((string) $r->query('client_id','')));
    $from  = $this->parseDate($r->query('from'));
    $to    = $this->parseDate($r->query('to'));
    $tz    = config('app.timezone','Asia/Jakarta');

    // Join ke payments agar dapat status & paid_at
    $q = HotspotOrder::query()
      ->leftJoin('payments','payments.order_id','=','hotspot_orders.order_id')
      ->select([
        'hotspot_orders.order_id',
        'hotspot_orders.client_id',
        'hotspot_orders.hotspot_voucher_id',
        'hotspot_orders.buyer_name',
        'hotspot_orders.buyer_email',
        'hotspot_orders.buyer_phone',
        'hotspot_orders.created_at',
        'payments.status as payment_status',
        'payments.paid_at as paid_at',
        'payments.amount as amount',
        'payments.currency as currency',
      ])
      ->when($cid !== '', fn($qq) => $qq->where('hotspot_orders.client_id',$cid))
      ->when($from, fn($qq) => $qq->whereDate('hotspot_orders.created_at','>=',$from))
      ->when($to,   fn($qq) => $qq->whereDate('hotspot_orders.created_at','<=',$to))
      ->orderBy('hotspot_orders.created_at');

    $filename = 'orders_'.$this->rangeSlug($from,$to).'_'.now()->format('Ymd_His').'.'.($fmt==='xlsx'?'xlsx':'csv');

    if ($fmt === 'xlsx' && class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
      return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\OrdersExport($q, $tz),
        $filename
      );
    }

    $headers = ['Order ID','Client','Voucher ID','Buyer','Email','Phone','Amount','Currency','Status','Paid At','Created At'];
    $mapper  = function ($o) use ($tz) {
      $paidAt = $o->paid_at ? Carbon::parse($o->paid_at)->timezone($tz)->format('Y-m-d H:i:s') : '';
      $crtAt  = $o->created_at ? Carbon::parse($o->created_at)->timezone($tz)->format('Y-m-d H:i:s') : '';
      return [
        $o->order_id,
        $o->client_id ?: 'DEFAULT',
        (int)$o->hotspot_voucher_id,
        $o->buyer_name ?: '',
        $o->buyer_email ?: '',
        $o->buyer_phone ?: '',
        (int)($o->amount ?? 0),
        $o->currency ?: 'IDR',
        strtoupper($o->payment_status ?: 'PENDING'),
        $paidAt,
        $crtAt,
      ];
    };

    return $this->streamCsv($q, $headers, $mapper, $filename);
  }

  private function streamCsv($query, array $headers, \Closure $map, string $filename)
  {
    $callback = function () use ($query, $headers, $map) {
      $out = fopen('php://output','w');
      // UTF-8 BOM supaya Excel Windows nyaman
      fwrite($out, "\xEF\xBB\xBF");
      fputcsv($out, $headers);
      $query->chunk(1000, function ($rows) use ($out, $map) {
        foreach ($rows as $row) {
          fputcsv($out, $map($row));
        }
      });
      fclose($out);
    };

    return Response::stream($callback, 200, [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="'.$filename.'"',
      'Cache-Control' => 'no-store, no-cache, must-revalidate',
    ]);
  }

  private function parseDate($v)
  {
    if (!$v) return null;
    try { return Carbon::parse($v)->format('Y-m-d'); } catch (\Throwable $e) { return null; }
  }

  private function rangeSlug($from,$to)
  {
    if ($from && $to)   return $from.'_'.$to;
    if ($from)          return $from.'_to';
    if ($to)            return 'to_'.$to;
    return 'all';
  }
}
