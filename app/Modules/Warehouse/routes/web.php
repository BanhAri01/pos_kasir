<?php

use App\Modules\Warehouse\Http\Controllers\BatchController;
use App\Modules\Warehouse\Http\Controllers\FormulaController;
use App\Modules\Warehouse\Http\Controllers\LocationController;
use App\Modules\Warehouse\Http\Controllers\ProductionController;
use App\Modules\Warehouse\Http\Controllers\WarehouseDashboardController;
use App\Modules\Warehouse\Http\Controllers\WarehouseReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'can:manage_stock'])->prefix('gudang')->group(function () {
    // Blok / rak penyimpanan di gudang aktif
    Route::middleware('module:multi_warehouse')->group(function () {
        Route::get('blok', [LocationController::class, 'index'])->name('warehouse.locations');
        Route::post('blok', [LocationController::class, 'store'])->name('warehouse.locations.store');
        Route::put('blok/{location}', [LocationController::class, 'update'])->name('warehouse.locations.update');
        Route::delete('blok/{location}', [LocationController::class, 'destroy'])->name('warehouse.locations.destroy');
        Route::put('barang/{product}/blok', [LocationController::class, 'assign'])->name('warehouse.locations.assign');
    });

    // Susut, selisih timbang, selisih hitung stok (tab mengikuti modul yang menyala)
    Route::get('laporan', [WarehouseReportController::class, 'index'])->name('warehouse.reports');

    // Beranda gudang: alur kerja + ringkasan
    Route::get('/', WarehouseDashboardController::class)->name('warehouse.dashboard');

    // Resep kemas ulang & olah (jenis resep mengikuti modul yang menyala)
    Route::get('resep', [FormulaController::class, 'index'])->name('warehouse.formulas');
    Route::get('resep/baru', [FormulaController::class, 'create'])->name('warehouse.formulas.create');
    Route::post('resep', [FormulaController::class, 'store'])->name('warehouse.formulas.store');
    Route::get('resep/{formula}/ubah', [FormulaController::class, 'edit'])->name('warehouse.formulas.edit');
    Route::put('resep/{formula}', [FormulaController::class, 'update'])->name('warehouse.formulas.update');
    Route::delete('resep/{formula}', [FormulaController::class, 'destroy'])->name('warehouse.formulas.destroy');

    Route::get('riwayat-olah', [ProductionController::class, 'index'])->name('warehouse.orders');
    Route::get('riwayat-olah/{uuid}', [ProductionController::class, 'show'])->whereUuid('uuid')->name('warehouse.orders.show');

    Route::middleware('module:repack')->group(function () {
        Route::get('kemas', [ProductionController::class, 'repack'])->name('warehouse.repack');
        Route::post('kemas', [ProductionController::class, 'storeRepack'])->name('warehouse.repack.store');
        Route::post('bongkar', [ProductionController::class, 'storeUnpack'])->name('warehouse.unpack.store');
    });

    Route::middleware('module:production')->group(function () {
        Route::get('olah', [ProductionController::class, 'production'])->name('warehouse.production');
        Route::post('olah', [ProductionController::class, 'storeProduction'])->name('warehouse.production.store');
    });

    Route::get('batch', [BatchController::class, 'index'])->middleware('module:batch_lot')->name('warehouse.batches');
});
