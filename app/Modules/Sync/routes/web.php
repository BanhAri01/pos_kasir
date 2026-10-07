<?php

use App\Modules\Sync\Http\Controllers\SyncConflictController;
use App\Modules\Sync\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'can:use_pos', 'throttle:120,1'])
    ->post('kasir/api/sync', SyncController::class)
    ->name('pos.api.sync');

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('perlu-dicek', [SyncConflictController::class, 'index'])->name('conflicts.index');
    Route::post('perlu-dicek/{conflict}/selesai', [SyncConflictController::class, 'resolve'])->name('conflicts.resolve');
});
