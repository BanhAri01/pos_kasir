<?php

use App\Modules\Report\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'can:view_reports', 'module:reports'])->group(function () {
    Route::get('laporan', [ReportController::class, 'index'])->name('reports.index');
    Route::get('laporan/excel', [ReportController::class, 'export'])->middleware('throttle:20,1')->name('reports.export');
    Route::get('laporan/cetak', [ReportController::class, 'print'])->name('reports.print');
});
