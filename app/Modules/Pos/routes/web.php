<?php

use App\Modules\Pos\Http\Controllers\InvoiceController;
use App\Modules\Pos\Http\Controllers\PosController;
use App\Modules\Pos\Http\Controllers\PosCustomerController;
use App\Modules\Pos\Http\Controllers\PosSaleController;
use App\Modules\Pos\Http\Controllers\PosShiftController;
use App\Modules\Pos\Http\Controllers\ReceiptController;
use App\Modules\Pos\Http\Controllers\SalesController;
use App\Modules\Pos\Http\Controllers\ShiftHistoryController;
use Illuminate\Support\Facades\Route;

// Struk digital publik (link WhatsApp), dilindungi tanda tangan URL.
Route::get('struk/{uuid}', [ReceiptController::class, 'show'])->middleware('signed')->name('receipt.show');

// Halaman Transaksi & Riwayat Kasir (pemilik/manajer, izin dicek di controller).
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('transaksi', [SalesController::class, 'index'])->name('sales.index');
    Route::get('transaksi/kasir', ShiftHistoryController::class)->name('shifts.index');
    Route::get('transaksi/{uuid}/faktur', InvoiceController::class)->whereUuid('uuid')->name('sales.invoice');
    Route::get('transaksi/{uuid}', [SalesController::class, 'show'])->whereUuid('uuid')->name('sales.show');
    Route::post('transaksi/{uuid}/batal', [SalesController::class, 'void'])->whereUuid('uuid')->name('sales.void');
    Route::post('transaksi/{uuid}/kembali', [SalesController::class, 'refund'])->whereUuid('uuid')->name('sales.refund');
    Route::get('transaksi/{uuid}/tukar', [SalesController::class, 'exchange'])->whereUuid('uuid')->middleware('module:returns_exchange')->name('sales.exchange');
    Route::post('transaksi/{uuid}/tukar', [SalesController::class, 'storeExchange'])->whereUuid('uuid')->middleware('module:returns_exchange')->name('sales.exchange.store');
});

Route::middleware(['auth', 'tenant', 'can:use_pos'])->group(function () {
    Route::get('kasir', [PosController::class, 'show'])->name('pos.show');

    // API untuk aplikasi kasir (JSON). Memakai sesi login yang sama + proteksi CSRF.
    Route::prefix('kasir/api')->name('pos.api.')->middleware('throttle:240,1')->group(function () {
        Route::get('bootstrap', [PosController::class, 'bootstrap'])->name('bootstrap');

        Route::get('sales', [PosSaleController::class, 'index'])->name('sales.index');
        Route::post('sales', [PosSaleController::class, 'store'])->name('sales.store');
        Route::get('sales/{uuid}', [PosSaleController::class, 'show'])->whereUuid('uuid')->name('sales.show');
        Route::post('sales/{uuid}/void', [PosSaleController::class, 'void'])->whereUuid('uuid')->name('sales.void');
        Route::post('sales/{uuid}/refund', [PosSaleController::class, 'refund'])->whereUuid('uuid')->name('sales.refund');

        Route::post('shifts', [PosShiftController::class, 'open'])->name('shifts.open');
        Route::get('shifts/{uuid}', [PosShiftController::class, 'summary'])->whereUuid('uuid')->name('shifts.summary');
        Route::post('shifts/{uuid}/cash', [PosShiftController::class, 'cash'])->whereUuid('uuid')->name('shifts.cash');
        Route::post('shifts/{uuid}/close', [PosShiftController::class, 'close'])->whereUuid('uuid')->name('shifts.close');

        Route::get('customers', [PosCustomerController::class, 'index'])->name('customers.index');
        Route::post('customers', [PosCustomerController::class, 'store'])->name('customers.store');
    });
});
