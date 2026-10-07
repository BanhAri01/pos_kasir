<?php

use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\PinLoginController;
use App\Modules\Auth\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('daftar', [RegisterController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('register.store');

    Route::get('masuk', [LoginController::class, 'create'])->name('login');
    Route::post('masuk', [LoginController::class, 'store'])->name('login.store');

    Route::get('masuk-pin', [PinLoginController::class, 'create'])->name('pin.create');
    Route::post('masuk-pin', [PinLoginController::class, 'store'])->name('pin.store');
});

Route::middleware('auth')->group(function () {
    Route::post('keluar', [LoginController::class, 'destroy'])->name('logout');
    Route::post('ganti-pengguna', [LoginController::class, 'switchUser'])->name('switch-user');
});
