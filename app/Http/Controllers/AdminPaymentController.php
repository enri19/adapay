<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Client;
use App\Http\Controllers\Concerns\ResolvesRoleAndClient;

class AdminPaymentController extends Controller
{
  use ResolvesRoleAndClient;

  public function index(Request $r)
  {
    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);
    $client  = $this->resolveClientId($user, $r->query('client_id',''));

    $status = strtoupper((string) $r->query('status', ''));
    $from   = $r->query('from');
    $to     = $r->query('to');
    $q      = trim((string) $r->query('q', ''));

    $rows = Payment::query()
      ->when($client !== '', fn($qq) => $qq->where('client_id', $client))
      ->when($status !== '', fn($qq) => $qq->where('status', $status))
      ->when($from, fn($qq) => $qq->whereDate('created_at', '>=', $from))
      ->when($to, fn($qq) => $qq->whereDate('created_at', '<=', $to))
      ->when($q !== '', function ($qq) use ($q) {
        $qq->where(function ($w) use ($q) {
          $w->where('order_id','like',"%{$q}%")
            ->orWhere('provider_ref','like',"%{$q}%");
        });
      })
      ->orderByDesc('created_at')
      ->paginate(20)
      ->appends($r->only('client_id','status','from','to','q'));

    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) $clientsQuery->where('client_id', $client);
    $clients = $clientsQuery->get();

    return view('admin.payments.index', compact('rows','clients','client','status','from','to','q'));
  }
}
