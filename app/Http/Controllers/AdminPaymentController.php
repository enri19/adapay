<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Client;
use App\Http\Controllers\Concerns\ResolvesRoleAndClient;
use Illuminate\Support\Facades\DB;

class AdminPaymentController extends Controller
{
  use ResolvesRoleAndClient;

  public function index(Request $r)
  {
    $user    = $r->user();
    $isAdmin = $this->userIsAdmin($user);
    $client  = $this->resolveClientId($user, $r->query('client_id',''));

    $status = strtoupper((string) $r->query('status', ''));
    $from   = $r->query('from');
    $to     = $r->query('to');
    $q      = trim((string) $r->query('q', ''));

    // default fee
    $feeDefault = (int) config('pay.admin_fee_flat_default', 0);
    // ekspresi fee: NULL/0 fallback ke default
    $feeExpr = 'COALESCE(NULLIF(clients.admin_fee_flat,0), '.$feeDefault.')';

    // base query
    $rows = Payment::query()
      ->leftJoin('clients','clients.client_id','=','payments.client_id')
      ->select('payments.*')
      ->selectRaw($feeExpr.' as admin_fee')
      ->selectRaw('payments.amount as gross')
      ->selectRaw('GREATEST(payments.amount - '.$feeExpr.',0) as net')
      ->when($client !== '', fn($qq) => $qq->where('payments.client_id', $client))
      ->when($status !== '', fn($qq) => $qq->where('payments.status', $status))
      ->when($from, fn($qq) => $qq->whereDate('payments.created_at', '>=', $from))
      ->when($to, fn($qq) => $qq->whereDate('payments.created_at', '<=', $to))
      ->when($q !== '', function ($qq) use ($q) {
        $qq->where(function ($w) use ($q) {
          $w->where('payments.order_id','like',"%{$q}%")
            ->orWhere('payments.provider_ref','like',"%{$q}%");
        });
      })
      ->orderByDesc('payments.created_at')
      ->paginate(20)
      ->appends($r->only('client_id','status','from','to','q'));

    // daftar clients (filter select box)
    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) $clientsQuery->where('client_id', $client);
    $clients = $clientsQuery->get();

    return view('admin.payments.index', compact(
      'rows','clients','client','status','from','to','q','isAdmin'
    ));
  }
}
