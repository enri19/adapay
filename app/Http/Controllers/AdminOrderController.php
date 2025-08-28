<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HotspotOrder;
use App\Models\Client;
use App\Http\Controllers\Concerns\ResolvesRoleAndClient;

class AdminOrderController extends Controller
{
  use ResolvesRoleAndClient;

  public function index(Request $r)
  {
    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);
    $client  = $this->resolveClientId($user, $r->query('client_id',''));

    $from = $r->query('from');
    $to   = $r->query('to');
    $q    = trim((string) $r->query('q', ''));

    $rows = HotspotOrder::query()
      ->leftJoin('payments','payments.order_id','=','hotspot_orders.order_id')
      ->select([
        'hotspot_orders.*',
        'payments.status as payment_status',
        'payments.paid_at as paid_at',
        'payments.amount as amount',
        'payments.currency as currency',
      ])
      ->when($client !== '', fn($qq) => $qq->where('hotspot_orders.client_id', $client))
      ->when($from, fn($qq) => $qq->whereDate('hotspot_orders.created_at', '>=', $from))
      ->when($to, fn($qq) => $qq->whereDate('hotspot_orders.created_at', '<=', $to))
      ->when($q !== '', function ($qq) use ($q) {
        $qq->where(function ($w) use ($q) {
          $w->where('hotspot_orders.order_id','like',"%{$q}%")
            ->orWhere('hotspot_orders.buyer_name','like',"%{$q}%")
            ->orWhere('hotspot_orders.buyer_email','like',"%{$q}%");
        });
      })
      ->orderByDesc('hotspot_orders.created_at')
      ->paginate(20)
      ->appends($r->only('client_id','from','to','q'));

    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) $clientsQuery->where('client_id', $client);
    $clients = $clientsQuery->get();

    return view('admin.orders.index', compact('rows','clients','client','from','to','q'));
  }
}
