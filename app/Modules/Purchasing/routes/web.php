<?php

use App\Modules\Purchasing\Http\Controllers\PurchaseController;
use App\Modules\Purchasing\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'can:manage_stock', 'module:supplier_purchase'])->group(function () {
    Route::get('pemasok', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('pemasok', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('pemasok/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('pemasok/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    Route::get('belanja', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('belanja/tambah', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('belanja', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('belanja/{uuid}', [PurchaseController::class, 'show'])->whereUuid('uuid')->name('purchases.show');
    Route::post('belanja/{uuid}/bayar', [PurchaseController::class, 'pay'])->whereUuid('uuid')->name('purchases.pay');
});
