<?php

use App\Modules\Billing\Services\Plans;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $user = auth()->user();

    if ($user) {
        return redirect()->route($user->is_super_admin ? 'admin.tenants.index' : 'dashboard');
    }

    return Inertia::render('Landing', [
        'catalog' => Plans::catalog(),
        'trialDays' => (int) config('hermes.trial_days'),
    ]);
})->name('home');
