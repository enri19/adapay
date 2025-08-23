<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
  public function index(Request $r)
  {
    $tz = config('app.timezone', 'Asia/Jakarta');
    $now = Carbon::now($tz);

    $clientsActive   = Client::where('is_active', 1)->count();
    $payments24h     = Payment::where('created_at', '>=', $now->copy()->subDay())->count();
    $pendingCount    = Payment::where('status', 'PENDING')->count();
    $todayRevenue    = (int) Payment::where('status','PAID')->whereDate('paid_at', $now->toDateString())->sum('amount');

    // Top 5 client 7 hari terakhir (PAID)
    $topClients7 = Payment::select('client_id', DB::raw('SUM(amount) as total'))
      ->where('status','PAID')
      ->where('paid_at','>=', $now->copy()->subDays(7))
      ->groupBy('client_id')
      ->orderByDesc('total')
      ->limit(5)
      ->get();

    // Mini chart: 7 hari terakhir (count & revenue)
    $start = $now->copy()->subDays(6)->startOfDay();
    $days = [];
    for ($i=0;$i<7;$i++){
      $days[] = $start->copy()->addDays($i)->format('Y-m-d');
    }

    $rawCount = Payment::select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'))
      ->where('created_at','>=',$start)
      ->groupBy(DB::raw('DATE(created_at)'))
      ->pluck('c','d')->toArray();

    $rawSum = Payment::select(DB::raw('DATE(paid_at) as d'), DB::raw('SUM(amount) as s'))
      ->where('status','PAID')
      ->where('paid_at','>=',$start)
      ->groupBy(DB::raw('DATE(paid_at)'))
      ->pluck('s','d')->toArray();

    $seriesCount = [];
    $seriesSum   = [];
    foreach ($days as $d){
      $seriesCount[] = (int)($rawCount[$d] ?? 0);
      $seriesSum[]   = (int)($rawSum[$d] ?? 0);
    }

    $recentPayments = Payment::orderByDesc('created_at')->limit(8)->get();

    return view('admin.dashboard', [
      'clientsActive' => $clientsActive,
      'payments24h'   => $payments24h,
      'pendingCount'  => $pendingCount,
      'todayRevenue'  => $todayRevenue,
      'topClients7'   => $topClients7,
      'days'          => $days,
      'seriesCount'   => $seriesCount,
      'seriesSum'     => $seriesSum,
      'recentPayments'=> $recentPayments,
    ]);
  }
}
