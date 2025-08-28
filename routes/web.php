<?php

// routes/web.php
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
Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

/**
 * Admin Area (uniform prefix + name)
 */
Route::prefix('admin')
  ->middleware('admin')
  ->as('admin.')
  ->group(function () {
    // Dashboard
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Vouchers
    Route::resource('vouchers', AdminVoucherController::class)->except(['show']);

    // Payments & Orders
    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/orders',   [AdminOrderController::class, 'index'])->name('orders.index');

    // Reports / Export
    Route::get('/payments/export', [AdminReportController::class, 'paymentsExport'])->name('payments.export');
    Route::get('/orders/export',   [AdminReportController::class, 'ordersExport'])->name('orders.export');

    // Hotspot Users
    Route::get('/hotspot-users', [AdminHotspotUsersController::class, 'index'])->name('hotspot-users.index');

    // Clients (CRUD)
    Route::prefix('clients')->as('clients.')->group(function () {
      Route::get('/',                [AdminClientController::class, 'index'])->name('index');
      Route::get('/create',          [AdminClientController::class, 'create'])->name('create');
      Route::post('/',               [AdminClientController::class, 'store'])->name('store');
      Route::get('/{client}/edit',   [AdminClientController::class, 'edit'])->name('edit');
      Route::put('/{client}',        [AdminClientController::class, 'update'])->name('update');
      Route::delete('/{client}',     [AdminClientController::class, 'destroy'])->name('destroy');
    });
  });
