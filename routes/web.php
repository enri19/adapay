<?php

// routes/web.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\VoucherController;

Route::get('/hotspot', [HotspotController::class, 'index'])->name('hotspot.index');
Route::get('/hotspot/order/{orderId}', [HotspotController::class, 'orderView'])->name('hotspot.order');
Route::get('/payments/return', [ReturnController::class, 'show'])->name('payments.return');

// Auth admin
Route::get('/admin/login', [AdminAuthController::class,'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class,'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class,'logout'])->name('admin.logout');

// Dashboard & fitur admin
Route::prefix('admin')->middleware('admin')->group(function () {
  Route::get('/', function(){ return view('admin.dashboard'); })->name('admin.dashboard');
  Route::resource('vouchers', VoucherController::class)->except(['show']);

  // clients CRUD (punya kamu sebelumnya)
  Route::name('clients.')->group(function(){
    Route::get('/clients', [ClientController::class,'index'])->name('index');
    Route::get('/clients/create', [ClientController::class,'create'])->name('create');
    Route::post('/clients', [ClientController::class,'store'])->name('store');
    Route::get('/clients/{client}/edit', [ClientController::class,'edit'])->name('edit');
    Route::put('/clients/{client}', [ClientController::class,'update'])->name('update');
    Route::delete('/clients/{client}', [ClientController::class,'destroy'])->name('destroy');
  });
});
