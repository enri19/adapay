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

    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);
    $clientFilter = null;

    // Non-admin wajib punya client_id → dashboard difilter ke client miliknya
    if (!$isAdmin) {
      $clientFilter = $this->requireUserClientId($user);
    }

    // === KPI Kecil ===
    $clientsActive = Client::query()
      ->when(!$isAdmin, function($q) use ($clientFilter) {
        $q->where('client_id', $clientFilter);
      })
      ->where('is_active', 1)
      ->count();

    $payments24h = Payment::query()
      ->when(!$isAdmin, function($q) use ($clientFilter) {
        $q->where('client_id', $clientFilter);
      })
      ->where('created_at', '>=', $now->copy()->subDay())
      ->count();

    $pendingCount = Payment::query()
      ->when(!$isAdmin, function($q) use ($clientFilter) {
        $q->where('client_id', $clientFilter);
      })
      ->where('status', 'PENDING')
      ->count();

    $todayRevenue = (int) Payment::query()
      ->when(!$isAdmin, function($q) use ($clientFilter) {
        $q->where('client_id', $clientFilter);
      })
      ->where('status','PAID')
      ->whereDate('paid_at', $now->toDateString())
      ->sum('amount');

    // === Top 5 client 7 hari (hanya relevan untuk admin) ===
    $topClients7 = collect();
    if ($isAdmin) {
      $topClients7 = Payment::select('client_id', DB::raw('SUM(amount) as total'))
        ->where('status','PAID')
        ->where('paid_at','>=', $now->copy()->subDays(7))
        ->groupBy('client_id')
        ->orderByDesc('total')
        ->limit(5)
        ->get();
    } else {
      // Untuk user: tampilkan ringkasan client miliknya sendiri
      $sumClient7 = Payment::query()
        ->where('status','PAID')
        ->where('client_id', $clientFilter)
        ->where('paid_at','>=', $now->copy()->subDays(7))
        ->sum('amount');
      $topClients7 = collect([ (object)[ 'client_id' => $clientFilter, 'total' => (int)$sumClient7 ] ]);
    }

    // === Mini chart: 7 hari terakhir (count & revenue) ===
    $start = $now->copy()->subDays(6)->startOfDay();
    $days = [];
    for ($i = 0; $i < 7; $i++) {
      $days[] = $start->copy()->addDays($i)->format('Y-m-d');
    }

    $rawCount = Payment::select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'))
      ->when(!$isAdmin, function($q) use ($clientFilter) {
        $q->where('client_id', $clientFilter);
      })
      ->where('created_at','>=',$start)
      ->groupBy(DB::raw('DATE(created_at)'))
      ->pluck('c','d')->toArray();

    $rawSum = Payment::select(DB::raw('DATE(paid_at) as d'), DB::raw('SUM(amount) as s'))
      ->when(!$isAdmin, function($q) use ($clientFilter) {
        $q->where('client_id', $clientFilter);
      })
      ->where('status','PAID')
      ->where('paid_at','>=',$start)
      ->groupBy(DB::raw('DATE(paid_at)'))
      ->pluck('s','d')->toArray();

    $seriesCount = [];
    $seriesSum   = [];
    foreach ($days as $d) {
      $seriesCount[] = (int)($rawCount[$d] ?? 0);
      $seriesSum[]   = (int)($rawSum[$d] ?? 0);
    }

    // === Recent payments (dibatasi untuk user) ===
    $recentPayments = Payment::query()
      ->when(!$isAdmin, function($q) use ($clientFilter) {
        $q->where('client_id', $clientFilter);
      })
      ->orderByDesc('created_at')
      ->limit(8)
      ->get();

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
      'isAdmin'       => $isAdmin,
      'clientFilter'  => $clientFilter,
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
