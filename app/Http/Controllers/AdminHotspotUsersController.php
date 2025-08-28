<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Client;

class AdminHotspotUsersController extends Controller
{
  public function index(Request $request)
  {
    $user = $request->user();
    $isAdmin = $this->userIsAdmin($user);

    // Admin boleh pilih via query; user dipaksa ke client miliknya
    $clientParam = (string) $request->query('client_id', '');
    $client = $isAdmin ? $clientParam : $this->requireUserClientId($user);

    $status = strtoupper((string) $request->query('status', '')); // '', PENDING, PAID, ...
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
      ])
      // Non-admin: kunci ke client miliknya
      ->when(!$isAdmin, function ($qq) use ($client) {
        $qq->where('o.client_id', $client);
      })
      // Filter client (admin bebas, user sudah dipaksa di atas)
      ->when($client !== '', function ($qq) use ($client) {
        $qq->where('o.client_id', $client);
      })
      ->when($status !== '', function ($qq) use ($status) {
        $qq->where('p.status', $status);
      })
      ->when($from, function ($qq) use ($from) {
        $qq->whereDate('o.created_at', '>=', $from);
      })
      ->when($to, function ($qq) use ($to) {
        $qq->whereDate('o.created_at', '<=', $to);
      })
      ->when($q, function ($qq) use ($q) {
        $qLike = '%' . str_replace(['%','_'], ['\%','\_'], (string) $q) . '%';
        $qq->where(function ($w) use ($qLike) {
          $w->where('o.order_id', 'like', $qLike)
            ->orWhere('u.username', 'like', $qLike)
            ->orWhere('o.buyer_name', 'like', $qLike)
            ->orWhere('o.buyer_phone', 'like', $qLike);
        });
      });

    $rows = $query
      ->orderByDesc('o.created_at')
      ->paginate(50)
      ->appends([
        'client_id' => $client, // paksa konsisten di pagination
        'status'    => $status,
        'from'      => $from,
        'to'        => $to,
        'q'         => $q,
      ]);

    // Dropdown clients:
    // - Admin: semua
    // - User: hanya client miliknya
    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) {
      $clientsQuery->where('client_id', $client);
    }
    $clients = $clientsQuery->get(['client_id', 'name']);

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

  private function userIsAdmin($user): bool
  {
    if (!$user) return false;
    if (method_exists($user, 'isAdmin')) return (bool) $user->isAdmin();
    if (isset($user->role)) return (string) $user->role === 'admin';
    return false;
  }

  private function requireUserClientId($user): string
  {
    // ADMIN: tidak perlu client filter → jangan abort, kembalikan '' agar query tidak di-filter client
    if ($this->userIsAdmin($user)) {
      return '';
    }

    // USER: wajib terikat client
    $clientId = strtoupper((string) ($user->client_id ?? ''));
    if ($clientId === '') {
      abort(403, 'User belum terikat ke client.');
    }
    return $clientId;
  }
}
