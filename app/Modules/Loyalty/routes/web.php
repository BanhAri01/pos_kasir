<?php

use App\Modules\Loyalty\Http\Controllers\LoyaltyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'can:manage_customers', 'module:loyalty'])->group(function () {
    Route::get('poin', [LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::put('poin/aturan', [LoyaltyController::class, 'update'])->name('loyalty.update');
    Route::post('poin/{customer}/ubah', [LoyaltyController::class, 'adjust'])->name('loyalty.adjust');
});
