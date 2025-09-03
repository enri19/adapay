<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
  public function index(Request $r)
  {
    $tz  = config('app.timezone', 'Asia/Jakarta');
    $now = Carbon::now($tz);

    $user = $r->user();

    // === Role flags ===
    $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ((string)($user->role ?? 'user') === 'superadmin');
    $isAdmin      = method_exists($user, 'isAdmin') ? $user->isAdmin() : ((string)($user->role ?? 'user') === 'admin');

    // Perspektif "admin" untuk rumus fee = admin || superadmin
    $isAdminPerspective = $isAdmin || $isSuperAdmin;

    // === Akses client (many-to-many) ===
    // superadmin: tanpa filter (lihat ->when di bawah); selain itu: filter by allowed_client_ids
    $allowedClientIds = $isSuperAdmin
      ? []
      : (property_exists($user, 'allowed_client_ids') ? $user->allowed_client_ids : $user->clients()->pluck('clients.client_id')->map(function ($v) { return strtoupper((string) $v); })->unique()->values()->all());

    // Untuk kompat tampilan lama yang mengharapkan string tunggal:
    $clientFilter = $isSuperAdmin
      ? ''
      : implode(',', $allowedClientIds); // contoh: "CLIENT001,CLIENT002"

    // === Normalisasi konstanta status ===
    $S_PAID    = \defined(\App\Models\Payment::class.'::S_PAID')    ? Payment::S_PAID    : 'PAID';
    $S_PENDING = \defined(\App\Models\Payment::class.'::S_PENDING') ? Payment::S_PENDING : 'PENDING';

    // === Fee expression ===
    $feeDefault = (int) config('pay.admin_fee_flat_default', 0);
    $feeExpr = 'COALESCE(NULLIF(clients.admin_fee_flat, 0), ' . $feeDefault . ')';

    // === Base query (payments) dengan filter akses ===
    $payBase = Payment::query()
      ->when(!empty($allowedClientIds), function ($q) use ($allowedClientIds) {
        $q->whereIn('payments.client_id', $allowedClientIds);
      });

    $payBaseJoin = (clone $payBase)
      ->leftJoin('clients', 'clients.client_id', '=', 'payments.client_id');

    // === KPI: clients active (hormati akses) ===
    $clientsActive = Client::query()
      ->when(!$isSuperAdmin, function ($q) use ($user) {
        $q->whereHas('users', function ($uq) use ($user) {
          $uq->where('users.id', $user->id);
        });
      })
      ->where('is_active', 1)
      ->count();

    // === KPI: payments 24 jam, pending ===
    $payments24h = (clone $payBase)
      ->where('payments.created_at', '>=', $now->copy()->subDay())
      ->count();

    $pendingCount = (clone $payBase)
      ->where('payments.status', $S_PENDING)
      ->count();

    // === Today revenue (perspektif admin vs user) ===
    $todayRevenue = (int) (clone $payBaseJoin)
      ->where('payments.status', $S_PAID)
      ->whereBetween('payments.paid_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
      ->selectRaw(
        'COALESCE(SUM(' . ($isAdminPerspective
          ? $feeExpr
          : 'GREATEST(payments.amount - ' . $feeExpr . ', 0)') . '), 0) as s'
      )
      ->value('s');

    // === 7 hari terakhir (seri & top) ===
    $start7 = $now->copy()->subDays(6)->startOfDay();
    $days = [];
    for ($i = 0; $i < 7; $i++) {
      $days[] = $start7->copy()->addDays($i)->format('Y-m-d');
    }

    $rawCount = (clone $payBase)
      ->select(DB::raw('DATE(payments.created_at) as d'), DB::raw('COUNT(*) as c'))
      ->where('payments.created_at', '>=', $start7)
      ->groupBy(DB::raw('DATE(payments.created_at)'))
      ->pluck('c', 'd')
      ->toArray();

    $rawSum = (clone $payBaseJoin)
      ->selectRaw('DATE(payments.paid_at) as d')
      ->selectRaw(($isAdminPerspective
        ? 'SUM(' . $feeExpr . ')'
        : 'SUM(GREATEST(payments.amount - ' . $feeExpr . ', 0))'
      ) . ' as s')
      ->where('payments.status', $S_PAID)
      ->where('payments.paid_at', '>=', $start7)
      ->groupBy(DB::raw('DATE(payments.paid_at)'))
      ->pluck('s', 'd')
      ->toArray();

    $seriesCount = [];
    $seriesSum   = [];
    foreach ($days as $d) {
      $seriesCount[] = (int) ($rawCount[$d] ?? 0);
      $seriesSum[]   = (int) ($rawSum[$d]   ?? 0);
    }
    $total7Revenue = array_sum($seriesSum);

    if ($isAdminPerspective) {
      // admin & superadmin: TOP by fee
      $topClients7 = (clone $payBaseJoin)
        ->where('payments.status', $S_PAID)
        ->where('payments.paid_at', '>=', $now->copy()->subDays(7)->startOfDay())
        ->groupBy('payments.client_id')
        ->select('payments.client_id', DB::raw('SUM(' . $feeExpr . ') as total'))
        ->orderByDesc('total')
        ->limit(5)
        ->get();
    } else {
      // user: total net (gabungan semua client yang ia miliki)
      $sumClient7 = (int) (clone $payBaseJoin)
        ->where('payments.status', $S_PAID)
        ->where('payments.paid_at', '>=', $now->copy()->subDays(7)->startOfDay())
        ->selectRaw('COALESCE(SUM(GREATEST(payments.amount - ' . $feeExpr . ', 0)), 0) as total_net')
        ->value('total_net');

      $topClients7 = collect([(object)[
        'client_id' => $clientFilter, // string gabungan utk kompat tampilan lama
        'total'     => $sumClient7,
      ]]);
    }

    // === Bulan berjalan ===
    $monthStart = $now->copy()->startOfMonth();
    $monthEnd   = $now->copy()->endOfDay();

    $monthRevenue = (int) (clone $payBaseJoin)
      ->where('payments.status', $S_PAID)
      ->whereBetween('payments.paid_at', [$monthStart, $monthEnd])
      ->selectRaw(
        'COALESCE(SUM(' . ($isAdminPerspective
          ? $feeExpr
          : 'GREATEST(payments.amount - ' . $feeExpr . ', 0)') . '), 0) as s'
      )
      ->value('s');

    $monthDays = [];
    $cursor = $monthStart->copy();
    while ($cursor->lte($monthEnd)) {
      $monthDays[] = $cursor->format('Y-m-d');
      $cursor->addDay();
    }

    $rawCountM = (clone $payBase)
      ->select(DB::raw('DATE(payments.created_at) as d'), DB::raw('COUNT(*) as c'))
      ->whereBetween('payments.created_at', [$monthStart, $monthEnd])
      ->groupBy(DB::raw('DATE(payments.created_at)'))
      ->pluck('c', 'd')
      ->toArray();

    $rawSumM = (clone $payBaseJoin)
      ->selectRaw('DATE(payments.paid_at) as d')
      ->selectRaw(($isAdminPerspective
        ? 'SUM(' . $feeExpr . ')'
        : 'SUM(GREATEST(payments.amount - ' . $feeExpr . ', 0))'
      ) . ' as s')
      ->where('payments.status', $S_PAID)
      ->whereBetween('payments.paid_at', [$monthStart, $monthEnd])
      ->groupBy(DB::raw('DATE(payments.paid_at)'))
      ->pluck('s', 'd')
      ->toArray();

    $seriesCountM = [];
    $seriesSumM   = [];
    foreach ($monthDays as $d) {
      $seriesCountM[] = (int) ($rawCountM[$d] ?? 0);
      $seriesSumM[]   = (int) ($rawSumM[$d]   ?? 0);
    }
    $totalMonthRevenue = array_sum($seriesSumM);

    if ($isAdminPerspective) {
      $topClientsM = (clone $payBaseJoin)
        ->where('payments.status', $S_PAID)
        ->whereBetween('payments.paid_at', [$monthStart, $monthEnd])
        ->groupBy('payments.client_id')
        ->select('payments.client_id', DB::raw('SUM(' . $feeExpr . ') as total'))
        ->orderByDesc('total')
        ->limit(10)
        ->get();
    } else {
      $sumClientM = (int) (clone $payBaseJoin)
        ->where('payments.status', $S_PAID)
        ->whereBetween('payments.paid_at', [$monthStart, $monthEnd])
        ->selectRaw('COALESCE(SUM(GREATEST(payments.amount - ' . $feeExpr . ', 0)), 0) as total_net')
        ->value('total_net');

      $topClientsM = collect([(object)[
        'client_id' => $clientFilter,
        'total'     => $sumClientM,
      ]]);
    }

    // === Recent payments ===
    $recentPayments = (clone $payBase)
      ->orderByDesc('payments.created_at')
      ->limit(8)
      ->get();

    return view('admin.dashboard', compact(
      'clientsActive',
      'payments24h',
      'pendingCount',
      'todayRevenue',
      'topClients7',
      'days',
      'seriesCount',
      'seriesSum',
      'recentPayments',
      // flags
      'isAdminPerspective',
      'clientFilter',
      // month
      'monthRevenue',
      'monthDays',
      'seriesCountM',
      'seriesSumM',
      'topClientsM',
      // totals
      'total7Revenue',
      'totalMonthRevenue'
    ));
  }
}
