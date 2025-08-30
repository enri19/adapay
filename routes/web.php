<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminClientController;
use App\Http\Controllers\AdminVoucherController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminHotspotUsersController;
use App\Http\Controllers\AdminUserController;

/**
 * Public
 */
Route::get('/', function () {
  return view('welcome');
})->name('welcome');

Route::get('/hotspot', [HotspotController::class, 'index'])->name('hotspot.index');
Route::get('/hotspot/order/{orderId}', [HotspotController::class, 'orderView'])->name('hotspot.order');
Route::get('/payments/return', [ReturnController::class, 'show'])->name('payments.return');

/**
 * Admin Auth
 */
Route::middleware('guest')->group(function () {
  Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
  Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
});
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->middleware('auth')->name('admin.logout');

/**
 * Admin Area
 * - Semua user login boleh akses /admin (dashboard), data akan difilter di controller.
 * - Resource/menu admin-only tetap dibatasi can:is-admin.
 */
Route::prefix('admin')->as('admin.')->middleware(['auth'])->group(function () {
  // Dashboard: selalu menuju ke sini setelah login
  Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

  // Vouchers
  Route::resource('vouchers', AdminVoucherController::class)->except(['show']);

  // Payments & Orders (halaman admin)
  Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
  Route::get('/orders',   [AdminOrderController::class, 'index'])->name('orders.index');

  // Reports / Export
  Route::get('/payments/export', [AdminReportController::class, 'paymentsExport'])->name('payments.export');
  Route::get('/orders/export',   [AdminReportController::class, 'ordersExport'])->name('orders.export');

  // Hotspot Users
  Route::get('/hotspot-users', [AdminHotspotUsersController::class, 'index'])->name('hotspot-users.index');

  // === Admin-only ===
  Route::middleware('can:is-admin')->group(function () {
    // Clients (CRUD)
    Route::prefix('clients')->as('clients.')->group(function () {
      Route::get('/',                [AdminClientController::class, 'index'])->name('index');
      Route::get('/create',          [AdminClientController::class, 'create'])->name('create');
      Route::post('/',               [AdminClientController::class, 'store'])->name('store');
      Route::get('/{client}/edit',   [AdminClientController::class, 'edit'])->name('edit');
      Route::put('/{client}',        [AdminClientController::class, 'update'])->name('update');
      Route::delete('/{client}',     [AdminClientController::class, 'destroy'])->name('destroy');
      Route::post('/{client:client_id}/router/import-vouchers', [AdminClientsController::class, 'importVouchers'])->name('router.import-vouchers');

      // Halaman alat router/hotspot
      Route::get('/{client}/tools',  [AdminClientController::class, 'tools'])->name('tools');

      // Aksi
      Route::post('/{client}/router/test',                [AdminClientController::class, 'routerTest'])->name('router.test');
      Route::post('/{client}/router/hotspot-test-user',   [AdminClientController::class, 'routerHotspotTestUser'])->name('router.hotspot-test-user');
      Route::post('/{client}/router/hotspot-login-test',  [AdminClientController::class, 'routerHotspotLoginTest'])->name('router.hotspot-login-test');
    });
    
    // Users Management
    Route::resource('users', AdminUserController::class)->except(['show']);
  });
});
