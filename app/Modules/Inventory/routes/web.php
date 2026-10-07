<?php

use App\Modules\Inventory\Http\Controllers\StockController;
use App\Modules\Inventory\Http\Controllers\StockDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'can:manage_stock'])->prefix('stok')->group(function () {
    Route::get('/', [StockController::class, 'index'])->name('stock.index');
    Route::get('riwayat/{product}', [StockController::class, 'history'])->name('stock.history');

    // Stok masuk (/stok/masuk) & stok keluar (/stok/keluar)
    Route::get('{type}', [StockController::class, 'adjust'])->whereIn('type', ['masuk', 'keluar'])
        ->name('stock.adjust');
    Route::post('penyesuaian', [StockDocumentController::class, 'storeAdjustment'])->name('stock.adjust.store');

    Route::get('hitung', [StockController::class, 'opname'])->name('stock.opname');
    Route::post('hitung', [StockDocumentController::class, 'storeOpname'])->name('stock.opname.store');

    Route::get('kirim', [StockDocumentController::class, 'transfers'])->name('stock.transfers');
    Route::get('kirim/baru', [StockDocumentController::class, 'createTransfer'])->name('stock.transfers.create');
    Route::post('kirim', [StockDocumentController::class, 'storeTransfer'])->name('stock.transfers.store');
    Route::post('kirim/{transfer}/terima', [StockDocumentController::class, 'receiveTransfer'])->name('stock.transfers.receive');
    Route::post('kirim/{transfer}/batal', [StockDocumentController::class, 'cancelTransfer'])->name('stock.transfers.cancel');
});
