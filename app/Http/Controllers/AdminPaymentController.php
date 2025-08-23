<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Client;

class AdminPaymentController extends Controller
{
  public function index(Request $r)
  {
    $client = strtoupper((string) $r->query('client_id', ''));
    $status = strtoupper((string) $r->query('status', ''));
    $from   = $r->query('from');
    $to     = $r->query('to');
    $q      = trim((string) $r->query('q', ''));

    $rows = Payment::query()
      ->when($client !== '', function($qq) use ($client){ $qq->where('client_id', $client); })
      ->when($status !== '', function($qq) use ($status){ $qq->where('status', $status); })
      ->when($from, function($qq) use ($from){ $qq->whereDate('created_at', '>=', $from); })
      ->when($to, function($qq) use ($to){ $qq->whereDate('created_at', '<=', $to); })
      ->when($q !== '', function($qq) use ($q){
        $qq->where(function($w) use ($q){
          $w->where('order_id','like',"%$q%")
            ->orWhere('provider_ref','like',"%$q%");
        });
      })
      ->orderByDesc('created_at')
      ->paginate(20)
      ->appends($r->all());

    $clients = Client::orderBy('client_id')->get();

    return view('admin.payments.index', compact('rows','clients','client','status','from','to','q'));
  }
}
