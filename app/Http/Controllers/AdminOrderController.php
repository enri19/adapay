<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HotspotOrder;
use App\Models\Client;

class AdminOrderController extends Controller
{
  public function index(Request $r)
  {
    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);

    // Admin boleh pilih via query; user dipaksa ke client miliknya
    $clientParam = strtoupper((string) $r->query('client_id', ''));
    $client = $isAdmin ? $clientParam : $this->requireUserClientId($user);

    $from = $r->query('from');
    $to   = $r->query('to');
    $q    = trim((string) $r->query('q', ''));

    $rows = HotspotOrder::query()
      ->leftJoin('payments', 'payments.order_id', '=', 'hotspot_orders.order_id')
      ->select([
        'hotspot_orders.*',
        'payments.status as payment_status',
        'payments.paid_at as paid_at',
        'payments.amount as amount',
        'payments.currency as currency',
      ])
      // Filter client (untuk user ini otomatis mengunci ke miliknya)
      ->when($client !== '', function ($qq) use ($client) {
        $qq->where('hotspot_orders.client_id', $client);
      })
      // Tanggal
      ->when($from, function ($qq) use ($from) {
        $qq->whereDate('hotspot_orders.created_at', '>=', $from);
      })
      ->when($to, function ($qq) use ($to) {
        $qq->whereDate('hotspot_orders.created_at', '<=', $to);
      })
      // Pencarian bebas
      ->when($q !== '', function ($qq) use ($q) {
        $qq->where(function ($w) use ($q) {
          $w->where('hotspot_orders.order_id', 'like', "%{$q}%")
            ->orWhere('hotspot_orders.buyer_name', 'like', "%{$q}%")
            ->orWhere('hotspot_orders.buyer_email', 'like', "%{$q}%");
        });
      })
      ->orderByDesc('hotspot_orders.created_at')
      ->paginate(20)
      ->appends($r->only('client_id','from','to','q'));

    // Dropdown clients:
    // - Admin: semua
    // - User: hanya client miliknya
    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) {
      $clientsQuery->where('client_id', $client);
    }
    $clients = $clientsQuery->get();

    return view('admin.orders.index', compact('rows','clients','client','from','to','q'));
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
