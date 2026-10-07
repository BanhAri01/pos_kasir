<?php

use App\Modules\Customer\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::post('pelanggan/{id}/pulihkan', [CustomerController::class, 'restore'])->whereNumber('id')->name('customers.restore');
    Route::resource('pelanggan', CustomerController::class)->parameters(['pelanggan' => 'customer'])->names('customers');
});
