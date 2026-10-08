<?php

use App\Modules\Admin\Http\Controllers\AdminTenantController;
use App\Modules\Admin\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSuperAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminTenantController::class, 'index'])->name('tenants.index');
    Route::get('usaha/{tenant}', [AdminTenantController::class, 'show'])->name('tenants.show');
    Route::post('usaha/{tenant}/perpanjang', [AdminTenantController::class, 'extend'])->name('tenants.extend');
    Route::post('usaha/{tenant}/tanpa-batas', [AdminTenantController::class, 'unlimited'])->name('tenants.unlimited');
    Route::post('usaha/{tenant}/bekukan', [AdminTenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('usaha/{tenant}/sandi-baru', [AdminTenantController::class, 'resetPassword'])->middleware('throttle:10,1')->name('tenants.password');
    Route::post('usaha/{tenant}/masuk', [AdminTenantController::class, 'impersonate'])->name('tenants.impersonate');
});

Route::middleware('auth')->post('admin/kembali', [AdminTenantController::class, 'leave'])->name('admin.impersonate.leave');
