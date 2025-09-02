<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\EmoneyController;

Route::post('/payments/qris', [PaymentController::class, 'createQris']);
Route::get('/payments/{orderId}', [PaymentController::class, 'show']);
// Route::post('/payments/{orderId}/refresh', [PaymentController::class, 'refreshStatus']);
Route::post('/payments/gopay', [PaymentController::class, 'createGopay']);
Route::post('/payments/ewallet', [EmoneyController::class, 'charge']);
Route::get('/payments/{orderId}/ewallet/qr', [PaymentController::class, 'ewalletQr']);
Route::get('/payments/{orderId}/qris.png', [PaymentController::class, 'qrisPng']);

// Webhook (public)
Route::post('/webhooks/midtrans', [WebhookController::class, 'handle']);

Route::post('/hotspot/checkout', [HotspotController::class, 'checkout']);
Route::get('/hotspot/credentials/{orderId}', [HotspotController::class, 'credentials']);
Route::get('/hotspot/vouchers', [HotspotController::class, 'vouchers'])->name('api.hotspot.vouchers');
