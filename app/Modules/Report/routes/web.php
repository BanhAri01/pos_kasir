<?php

use App\Modules\Report\Http\Controllers\OwnerReportController;
use App\Modules\Report\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'can:view_reports', 'module:reports'])->group(function () {
    Route::get('laporan', [ReportController::class, 'index'])->name('reports.index');
    Route::get('laporan/excel', [ReportController::class, 'export'])->middleware('throttle:20,1')->name('reports.export');
    Route::get('laporan/cetak', [ReportController::class, 'print'])->name('reports.print');
});

Route::middleware(['auth', 'tenant', 'can:manage_business'])->group(function () {
    Route::get('laporan-wa', [OwnerReportController::class, 'edit'])->name('owner-report.edit');
    Route::put('laporan-wa', [OwnerReportController::class, 'update'])->name('owner-report.update');
    Route::post('laporan-wa/kirim', [OwnerReportController::class, 'test'])->middleware('throttle:5,1')->name('owner-report.test');
});
