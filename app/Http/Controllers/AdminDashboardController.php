<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Concerns\ResolvesRoleAndClient;

class AdminDashboardController extends Controller
{
  use ResolvesRoleAndClient;

  public function index(Request $r)
  {
    $tz  = config('app.timezone', 'Asia/Jakarta');
    $now = Carbon::now($tz);

    $user         = $r->user();
    $isAdmin      = $this->userIsAdmin($user);
    $clientFilter = $this->resolveClientId($user, ''); // admin: '', user: client-nya

    // Normalisasi konstanta status
    $S_PAID    = \defined(\App\Models\Payment::class.'::S_PAID')    ? Payment::S_PAID    : 'PAID';
    $S_PENDING = \defined(\App\Models\Payment::class.'::S_PENDING') ? Payment::S_PENDING : 'PENDING';

    // Default admin fee bila kolom client NULL/tidak ada
    $feeDefault = (int) config('pay.admin_fee_flat_default', 0);
    // Ekspresi SQL admin fee per client (ambil dari clients.admin_fee_flat, fallback default)
    $feeExpr = 'COALESCE(NULLIF(clients.admin_fee_flat, 0), ' . $feeDefault . ')';

    // Base query payments sesuai scope client
    $payBase = Payment::query()
      ->when($clientFilter !== '', fn($q) => $q->where('payments.client_id', $clientFilter));

    // Versi join ke clients supaya bisa hitung fee via SQL
    $payBaseJoin = (clone $payBase)
      ->leftJoin('clients', 'clients.client_id', '=', 'payments.client_id');

    // ===== Ringkas (KPI) =====
    $clientsActive = Client::query()
      ->when($clientFilter !== '', fn($q) => $q->where('client_id', $clientFilter))
      ->where('is_active', 1)
      ->count();

    $payments24h = (clone $payBase)
      ->where('payments.created_at', '>=', $now->copy()->subDay())
      ->count();

    $pendingCount = (clone $payBase)
      ->where('payments.status', $S_PENDING)
      ->count();

    // Revenue hari ini:
    //   Admin  = SUM(admin_fee_flat)
    //   Client = SUM(GREATEST(amount - admin_fee_flat, 0))
    $todayRevenue = (int) (clone $payBaseJoin)
      ->where('payments.status', $S_PAID)
      ->whereBetween('payments.paid_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
      ->selectRaw(
        'COALESCE(SUM(' . ($isAdmin
          ? $feeExpr
          : 'GREATEST(payments.amount - ' . $feeExpr . ', 0)') . '), 0) as s'
      )
      ->value('s');

    // ===== 7 Hari Terakhir =====
    $start7 = $now->copy()->subDays(6)->startOfDay();
    $days = [];
    for ($i = 0; $i < 7; $i++) {
      $days[] = $start7->copy()->addDays($i)->format('Y-m-d');
    }

    // Count per created_at
    $rawCount = (clone $payBase)
      ->select(DB::raw('DATE(payments.created_at) as d'), DB::raw('COUNT(*) as c'))
      ->where('payments.created_at', '>=', $start7)
      ->groupBy(DB::raw('DATE(payments.created_at)'))
      ->pluck('c', 'd')
      ->toArray();

    // Sum per paid_at (fee untuk admin, net untuk client)
    $rawSum = (clone $payBaseJoin)
      ->selectRaw('DATE(payments.paid_at) as d')
      ->selectRaw(($isAdmin
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

    // Top clients 7 hari
    if ($isAdmin) {
      $topClients7 = (clone $payBaseJoin)
        ->where('payments.status', $S_PAID)
        ->where('payments.paid_at', '>=', $now->copy()->subDays(7)->startOfDay())
        ->groupBy('payments.client_id')
        ->select('payments.client_id', DB::raw('SUM(' . $feeExpr . ') as total'))
        ->orderByDesc('total')
        ->limit(5)
        ->get();
    } else {
      $sumClient7 = (int) (clone $payBaseJoin)
        ->where('payments.status', $S_PAID)
        ->where('payments.paid_at', '>=', $now->copy()->subDays(7)->startOfDay())
        ->selectRaw('COALESCE(SUM(GREATEST(payments.amount - ' . $feeExpr . ', 0)), 0) as total_net')
        ->value('total_net');

      $topClients7 = collect([(object)[
        'client_id' => $clientFilter,
        'total'     => $sumClient7,
      ]]);
    }

    // ===== Bulan Berjalan (1 bulan) =====
    $monthStart = $now->copy()->startOfMonth();
    $monthEnd   = $now->copy()->endOfDay();

    $monthRevenue = (int) (clone $payBaseJoin)
      ->where('payments.status', $S_PAID)
      ->whereBetween('payments.paid_at', [$monthStart, $monthEnd])
      ->selectRaw(
        'COALESCE(SUM(' . ($isAdmin
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
      ->selectRaw(($isAdmin
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

    if ($isAdmin) {
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

    // ===== Tabel pembayaran terbaru =====
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
      'isAdmin',
      'clientFilter',
      'monthRevenue',
      'monthDays',
      'seriesCountM',
      'seriesSumM',
      'topClientsM'
    ));
  }
}
