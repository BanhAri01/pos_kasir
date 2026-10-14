<?php

use App\Modules\Billing\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('langganan', [BillingController::class, 'index'])->name('billing.index');
    Route::post('langganan/bayar', [BillingController::class, 'checkout'])->middleware('throttle:10,1')->name('billing.checkout');
    Route::get('langganan/selesai', [BillingController::class, 'finish'])->name('billing.finish');
});

Route::post('webhook/midtrans', [BillingController::class, 'webhook'])->middleware('throttle:120,1')->name('billing.webhook');
Route::get('dev/bayar-palsu/{reference}', [BillingController::class, 'fakePay'])->name('billing.fake-pay');
