<?php

use App\Modules\Outlet\Http\Controllers\OutletController;
use App\Modules\Outlet\Http\Controllers\OutletSwitchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::resource('outlet', OutletController::class)
        ->except('show')
        ->parameters(['outlet' => 'outlet'])
        ->names('outlets');

    Route::post('outlet-aktif', OutletSwitchController::class)->name('outlets.switch');

    Route::post('outlet/{id}/pulihkan', [OutletController::class, 'restore'])
        ->whereNumber('id')
        ->name('outlets.restore');
});
