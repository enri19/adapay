<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HotspotUser;
use App\Models\HotspotOrder;
use App\Models\Client;

class AdminHotspotUsersController extends Controller
{
  public function index(Request $request)
  {
    $client = $request->query('client_id');
    $status = $request->query('status'); // '', 'READY', 'PENDING'
    $from   = $request->query('from');   // Y-m-d
    $to     = $request->query('to');     // Y-m-d
    $q      = $request->query('q');

    $query = HotspotUser::query()
      ->leftJoin('hotspot_orders as o', 'o.order_id', '=', 'hotspot_users.order_id')
      ->leftJoin('clients as c', 'c.client_id', '=', 'o.client_id')
      ->select([
        'hotspot_users.*',
        'o.client_id as client_id',
        'c.name as client_name',
      ]);

    if ($client) {
      $query->where('o.client_id', $client);
    }

    if ($status === 'READY') {
      $query->whereNotNull('hotspot_users.provisioned_at');
    } elseif ($status === 'PENDING') {
      $query->whereNull('hotspot_users.provisioned_at');
    }

    if ($from) {
      $query->whereDate('hotspot_users.created_at', '>=', $from);
    }
    if ($to) {
      $query->whereDate('hotspot_users.created_at', '<=', $to);
    }

    if ($q) {
      $qLike = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
      $query->where(function ($w) use ($qLike) {
        $w->where('hotspot_users.order_id', 'like', $qLike)
          ->orWhere('hotspot_users.username', 'like', $qLike);
      });
    }

    $rows = $query
      ->orderByDesc('hotspot_users.created_at')
      ->paginate(50)
      ->appends($request->query());

    $clients = Client::orderBy('client_id')->get(['client_id', 'name']);

    return view('admin.hotspot_users.index', [
      'rows'    => $rows,
      'clients' => $clients,
      'client'  => $client,
      'status'  => $status,
      'from'    => $from,
      'to'      => $to,
      'q'       => $q,
    ]);
  }
}
