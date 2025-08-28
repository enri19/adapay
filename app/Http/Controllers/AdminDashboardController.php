<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\Concerns\ResolvesRoleAndClient;

class AdminDashboardController extends Controller
{
  use ResolvesRoleAndClient;

  public function index(Request $r)
  {
    $tz = config('app.timezone', 'Asia/Jakarta');
    $now = Carbon::now($tz);

    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);
    $clientFilter = $this->resolveClientId($user, ''); // admin: '', user: client-nya

    $clientsActive = Client::query()
      ->when($clientFilter !== '', fn($q) => $q->where('client_id', $clientFilter))
      ->where('is_active', 1)
      ->count();

    $payments24h = Payment::query()
      ->when($clientFilter !== '', fn($q) => $q->where('client_id', $clientFilter))
      ->where('created_at', '>=', $now->copy()->subDay())
      ->count();

    $pendingCount = Payment::query()
      ->when($clientFilter !== '', fn($q) => $q->where('client_id', $clientFilter))
      ->where('status', 'PENDING')
      ->count();

    $todayRevenue = (int) Payment::query()
      ->when($clientFilter !== '', fn($q) => $q->where('client_id', $clientFilter))
      ->where('status','PAID')
      ->whereDate('paid_at', $now->toDateString())
      ->sum('amount');

    // Top 5 client 7 hari (admin) / ringkasan client sendiri (user)
    if ($isAdmin) {
      $topClients7 = Payment::select('client_id', DB::raw('SUM(amount) as total'))
        ->where('status','PAID')
        ->where('paid_at','>=', $now->copy()->subDays(7))
        ->groupBy('client_id')
        ->orderByDesc('total')
        ->limit(5)->get();
    } else {
      $sumClient7 = Payment::query()
        ->where('status','PAID')
        ->where('client_id', $clientFilter)
        ->where('paid_at','>=', $now->copy()->subDays(7))
        ->sum('amount');
      $topClients7 = collect([(object)['client_id'=>$clientFilter,'total'=>(int)$sumClient7]]);
    }

    // Mini chart 7 hari
    $start = $now->copy()->subDays(6)->startOfDay();
    $days = [];
    for ($i=0; $i<7; $i++) $days[] = $start->copy()->addDays($i)->format('Y-m-d');

    $rawCount = Payment::select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'))
      ->when($clientFilter !== '', fn($q) => $q->where('client_id', $clientFilter))
      ->where('created_at','>=',$start)
      ->groupBy(DB::raw('DATE(created_at)'))
      ->pluck('c','d')->toArray();

    $rawSum = Payment::select(DB::raw('DATE(paid_at) as d'), DB::raw('SUM(amount) as s'))
      ->when($clientFilter !== '', fn($q) => $q->where('client_id', $clientFilter))
      ->where('status','PAID')
      ->where('paid_at','>=',$start)
      ->groupBy(DB::raw('DATE(paid_at)'))
      ->pluck('s','d')->toArray();

    $seriesCount = [];
    $seriesSum = [];
    foreach ($days as $d) {
      $seriesCount[] = (int)($rawCount[$d] ?? 0);
      $seriesSum[]   = (int)($rawSum[$d] ?? 0);
    }

    $recentPayments = Payment::query()
      ->when($clientFilter !== '', fn($q) => $q->where('client_id', $clientFilter))
      ->orderByDesc('created_at')->limit(8)->get();

    return view('admin.dashboard', compact(
      'clientsActive','payments24h','pendingCount','todayRevenue',
      'topClients7','days','seriesCount','seriesSum','recentPayments',
      'isAdmin','clientFilter'
    ));
  }
}
