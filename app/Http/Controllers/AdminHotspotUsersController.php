<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Client;
use App\Http\Controllers\Concerns\ResolvesRoleAndClient;

class AdminHotspotUsersController extends Controller
{
  use ResolvesRoleAndClient;

  public function index(Request $request)
  {
    $user = $request->user();
    $isAdmin = $this->userIsAdmin($user);
    $client  = $this->resolveClientId($user, $request->query('client_id',''));

    $status = strtoupper((string) $request->query('status', ''));
    $from   = $request->query('from');
    $to     = $request->query('to');
    $q      = $request->query('q');

    $query = DB::table('hotspot_orders as o')
      ->leftJoin('payments as p', 'p.order_id', '=', 'o.order_id')
      ->leftJoin('hotspot_users as u', 'u.order_id', '=', 'o.order_id')
      ->leftJoin('clients as c', 'c.client_id', '=', 'o.client_id')
      ->select([
        'o.order_id','o.client_id','o.buyer_name','o.buyer_email','o.buyer_phone',
        'o.created_at as order_created_at',
        'p.status as pay_status','p.paid_at','p.amount','p.currency',
        'u.username','u.password','u.profile','u.duration_minutes','u.created_at as user_created_at',
        'c.name as client_name',
      ])
      ->when($client !== '', fn($qq) => $qq->where('o.client_id', $client))
      ->when($status !== '', fn($qq) => $qq->where('p.status', $status))
      ->when($from, fn($qq) => $qq->whereDate('o.created_at', '>=', $from))
      ->when($to, fn($qq) => $qq->whereDate('o.created_at', '<=', $to))
      ->when($q, function ($qq) use ($q) {
        $qLike = '%' . str_replace(['%','_'], ['\%','\_'], (string) $q) . '%';
        $qq->where(function ($w) use ($qLike) {
          $w->where('o.order_id', 'like', $qLike)
            ->orWhere('u.username', 'like', $qLike)
            ->orWhere('o.buyer_name', 'like', $qLike)
            ->orWhere('o.buyer_phone', 'like', $qLike);
        });
      });

    $rows = $query->orderByDesc('o.created_at')
      ->paginate(50)
      ->appends([
        'client_id'=>$client,'status'=>$status,'from'=>$from,'to'=>$to,'q'=>$q,
      ]);

    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) $clientsQuery->where('client_id', $client);
    $clients = $clientsQuery->get(['client_id','name']);

    return view('admin.hotspot_users.index', compact('rows','clients','client','status','from','to','q'));
  }
}
