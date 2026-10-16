<?php

use App\Modules\EggCollector\Http\Controllers\EggPriceController;
use App\Modules\EggCollector\Http\Controllers\EggSortingController;
use App\Modules\EggCollector\Http\Controllers\EggSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'module:egg_collector'])->prefix('pengepul')->name('egg.')->group(function () {
    // Harga Hari Ini & jenis telur
    Route::middleware('can:manage_products')->group(function () {
        Route::get('harga', [EggPriceController::class, 'index'])->name('prices');
        Route::post('harga', [EggPriceController::class, 'store'])->name('prices.store');
        Route::get('jenis-telur', [EggPriceController::class, 'types'])->name('types');
        Route::put('jenis-telur', [EggPriceController::class, 'updateTypes'])->name('types.update');
        Route::put('satuan', [EggPriceController::class, 'updateUnits'])->name('units.update');
    });

    // Ringkasan uang: untung, piutang pembeli, utang ke peternak
    Route::get('ringkasan', EggSummaryController::class)->middleware('can:view_reports')->name('summary');

    // Sortir telur campur (pemilik, manajer, dan kasir)
    Route::get('sortir', [EggSortingController::class, 'create'])->name('sortings.create');
    Route::post('sortir', [EggSortingController::class, 'store'])->middleware('throttle:60,1')->name('sortings.store');
    Route::get('sortir/{uuid}', [EggSortingController::class, 'show'])->whereUuid('uuid')->name('sortings.show');
});
