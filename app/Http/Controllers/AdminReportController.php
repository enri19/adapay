<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\HotspotOrder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Controllers\Concerns\ResolvesRoleAndClient;

class AdminReportController extends Controller
{
  use ResolvesRoleAndClient;

  public function paymentsExport(Request $r)
  {
    $user = $r->user();
    $client = $this->resolveClientId($user, $r->query('client_id',''));
    $status = strtoupper((string) $r->query('status',''));
    $from   = $r->query('from');
    $to     = $r->query('to');

    $q = Payment::query()
      ->when($client !== '', fn($qq) => $qq->where('client_id', $client))
      ->when($status !== '', fn($qq) => $qq->where('status', $status))
      ->when($from, fn($qq) => $qq->whereDate('created_at', '>=', $from))
      ->when($to, fn($qq) => $qq->whereDate('created_at', '<=', $to))
      ->orderBy('created_at');

    // Simple CSV streaming (tanpa paket)
    $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="payments.csv"'];
    return new StreamedResponse(function () use ($q) {
      $out = fopen('php://output', 'w');
      fputcsv($out, ['created_at','order_id','client_id','amount','currency','status','paid_at','provider_ref']);
      $q->chunk(1000, function ($rows) use ($out) {
        foreach ($rows as $p) {
          fputcsv($out, [
            optional($p->created_at)->format('Y-m-d H:i:s'),
            $p->order_id, $p->client_id, (int)$p->amount, $p->currency, $p->status,
            $p->paid_at, $p->provider_ref,
          ]);
        }
      });
      fclose($out);
    }, 200, $headers);
  }

  public function ordersExport(Request $r)
  {
    $user = $r->user();
    $client = $this->resolveClientId($user, $r->query('client_id',''));
    $from = $r->query('from');
    $to   = $r->query('to');

    $q = HotspotOrder::query()
      ->when($client !== '', fn($qq) => $qq->where('client_id', $client))
      ->when($from, fn($qq) => $qq->whereDate('created_at', '>=', $from))
      ->when($to, fn($qq) => $qq->whereDate('created_at', '<=', $to))
      ->orderBy('created_at');

    $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="orders.csv"'];
    return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($q) {
      $out = fopen('php://output', 'w');
      fputcsv($out, ['created_at','order_id','client_id','buyer_name','buyer_email','buyer_phone']);
      $q->chunk(1000, function ($rows) use ($out) {
        foreach ($rows as $o) {
          fputcsv($out, [
            optional($o->created_at)->format('Y-m-d H:i:s'),
            $o->order_id, $o->client_id, $o->buyer_name, $o->buyer_email, $o->buyer_phone,
          ]);
        }
      });
      fclose($out);
    }, 200, $headers);
  }
}
