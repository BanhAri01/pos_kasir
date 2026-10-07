<?php

use App\Models\User;
use App\Modules\Staff\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

// {staff} hanya mencari karyawan di usaha yang sama; usaha lain = 404.
Route::bind('staff', fn (string $value) => User::sameTenant()->findOrFail((int) $value));

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::resource('karyawan', StaffController::class)
        ->except('show')
        ->parameters(['karyawan' => 'staff'])
        ->names('staff');

    Route::post('karyawan/{id}/pulihkan', [StaffController::class, 'restore'])
        ->whereNumber('id')
        ->name('staff.restore');
});
