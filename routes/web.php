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

Route::get('/', function () {
  return view('welcome');
})->name('welcome');

Route::get('/hotspot', [HotspotController::class, 'index'])->name('hotspot.index');
Route::get('/hotspot/order/{orderId}', [HotspotController::class, 'orderView'])->name('hotspot.order');
Route::get('/payments/return', [ReturnController::class, 'show'])->name('payments.return');

// Auth admin
Route::get('/admin/login', [AdminAuthController::class,'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class,'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class,'logout'])->name('admin.logout');

// Dashboard & fitur admin
Route::prefix('admin')->middleware('admin')->group(function () {
  Route::get('/', [AdminDashboardController::class,'index'])->name('admin.dashboard')->middleware('admin');
  Route::resource('vouchers', AdminVoucherController::class)->except(['show']);

  Route::get('/payments', [AdminPaymentController::class,'index'])->name('admin.payments.index');
  Route::get('/orders',   [AdminOrderController::class,'index'])->name('admin.orders.index');
  
  Route::get('/payments/export', [AdminReportController::class,'paymentsExport'])->name('admin.payments.export');
  Route::get('/orders/export',   [AdminReportController::class,'ordersExport'])->name('admin.orders.export');

  // clients CRUD (punya kamu sebelumnya)
  Route::name('clients.')->group(function(){
    Route::get('/clients', [AdminClientController::class,'index'])->name('index');
    Route::get('/clients/create', [AdminClientController::class,'create'])->name('create');
    Route::post('/clients', [AdminClientController::class,'store'])->name('store');
    Route::get('/clients/{client}/edit', [AdminClientController::class,'edit'])->name('edit');
    Route::put('/clients/{client}', [AdminClientController::class,'update'])->name('update');
    Route::delete('/clients/{client}', [AdminClientController::class,'destroy'])->name('destroy');
  });
});
