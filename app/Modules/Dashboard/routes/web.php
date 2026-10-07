<?php

use App\Modules\Dashboard\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('beranda', DashboardController::class)->name('dashboard');
});
