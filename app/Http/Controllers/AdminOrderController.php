<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HotspotOrder;
use App\Models\Client;

class AdminOrderController extends Controller
{
  public function index(Request $r)
  {
    $client = strtoupper((string) $r->query('client_id', ''));
    $from   = $r->query('from');
    $to     = $r->query('to');
    $q      = trim((string) $r->query('q', ''));

    $rows = HotspotOrder::query()
      ->leftJoin('payments','payments.order_id','=','hotspot_orders.order_id')
      ->select([
        'hotspot_orders.*',
        'payments.status as payment_status',
        'payments.paid_at as paid_at',
        'payments.amount as amount',
        'payments.currency as currency',
      ])
      ->when($client !== '', function($qq) use ($client){ $qq->where('hotspot_orders.client_id', $client); })
      ->when($from, function($qq) use ($from){ $qq->whereDate('hotspot_orders.created_at', '>=', $from); })
      ->when($to, function($qq) use ($to){ $qq->whereDate('hotspot_orders.created_at', '<=', $to); })
      ->when($q !== '', function($qq) use ($q){
        $qq->where(function($w) use ($q){
          $w->where('hotspot_orders.order_id','like',"%$q%")
            ->orWhere('hotspot_orders.buyer_name','like',"%$q%")
            ->orWhere('hotspot_orders.buyer_email','like',"%$q%");
        });
      })
      ->orderByDesc('hotspot_orders.created_at')
      ->paginate(20)
      ->appends($r->all());

    $clients = Client::orderBy('client_id')->get();

    return view('admin.orders.index', compact('rows','clients','client','from','to','q'));
  }
}
