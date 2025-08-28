<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Client;

class AdminHotspotUsersController extends Controller
{
  public function index(Request $request)
  {
    $client = $request->query('client_id');
    $status = strtoupper((string) $request->query('status', '')); // '', PENDING, PAID, FAILED, ...
    $from   = $request->query('from');   // Y-m-d
    $to     = $request->query('to');     // Y-m-d
    $q      = $request->query('q');

    $query = DB::table('hotspot_orders as o')
      ->leftJoin('payments as p', 'p.order_id', '=', 'o.order_id')
      ->leftJoin('hotspot_users as u', 'u.order_id', '=', 'o.order_id')
      ->leftJoin('clients as c', 'c.client_id', '=', 'o.client_id')
      ->select([
        'o.order_id',
        'o.client_id',
        'o.buyer_name',
        'o.buyer_email',
        'o.buyer_phone',
        'o.created_at as order_created_at',
        'p.status as pay_status',
        'p.paid_at',
        'p.amount',
        'p.currency',
        'u.username',
        'u.password',
        'u.profile',
        'u.duration_minutes',
        'u.created_at as user_created_at',
        'c.name as client_name',
      ]);

    if ($client) {
      $query->where('o.client_id', $client);
    }
    if ($status !== '') {
      $query->where('p.status', $status);
    }
    if ($from) {
      $query->whereDate('o.created_at', '>=', $from);
    }
    if ($to) {
      $query->whereDate('o.created_at', '<=', $to);
    }
    if ($q) {
      $qLike = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
      $query->where(function ($w) use ($qLike) {
        $w->where('o.order_id', 'like', $qLike)
          ->orWhere('u.username', 'like', $qLike)
          ->orWhere('o.buyer_name', 'like', $qLike)
          ->orWhere('o.buyer_phone', 'like', $qLike);
      });
    }

    $rows = $query
      ->orderByDesc('o.created_at')
      ->paginate(50)
      ->appends($request->query());

    $clients = Client::orderBy('client_id')->get(['client_id', 'name']);

    return view('admin.hotspot_users.index', [
      'rows'    => $rows,
      'clients' => $clients,
      'client'  => $client,
      'status'  => $status,   // penting buat filter di Blade
      'from'    => $from,
      'to'      => $to,
      'q'       => $q,
    ]);
  }
}
