<?php

use App\Modules\Settings\Http\Controllers\ModuleSettingController;
use App\Modules\Settings\Http\Controllers\PaymentMethodController;
use App\Modules\Settings\Http\Controllers\PreferenceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'tenant'])->group(function () {
    // Menu "Lainnya" di navigasi bawah.
    Route::get('lainnya', fn () => Inertia::render('More'))->name('more');

    Route::get('pengaturan/fitur', [ModuleSettingController::class, 'index'])->name('modules.index');
    Route::put('pengaturan/fitur/{code}', [ModuleSettingController::class, 'update'])
        ->where('code', '[a-z_]+')
        ->name('modules.update');

    Route::get('pengaturan/cara-bayar', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    Route::post('pengaturan/cara-bayar', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::put('pengaturan/cara-bayar/{method}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');

    Route::get('pengaturan/tampilan', [PreferenceController::class, 'edit'])->name('preferences.edit');
    Route::put('pengaturan/tampilan', [PreferenceController::class, 'update'])->name('preferences.update');
    Route::post('tur/ulangi', [PreferenceController::class, 'restartTour'])->name('tour.restart');

    // Katalog komponen UI, hanya saat development (untuk mengecek tampilan).
    if (app()->environment('local')) {
        Route::get('dev/komponen', fn () => Inertia::render('Dev/Components'))->name('dev.components');
    }
});
