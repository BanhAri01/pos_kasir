<?php

use App\Modules\Expense\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'can:manage_expenses', 'module:expenses'])->group(function () {
    Route::get('pengeluaran', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('pengeluaran', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('pengeluaran/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::post('pengeluaran/jenis', [ExpenseController::class, 'storeCategory'])->name('expenses.categories.store');
});
