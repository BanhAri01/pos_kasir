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

Route::get('robots.txt', fn () => response(implode("\n", [
    'User-agent: *',
    'Allow: /$',
    'Allow: /daftar',
    'Allow: /masuk',
    'Disallow: /',
    '',
    'Sitemap: '.url('sitemap.xml'),
    '',
]), 200, ['Content-Type' => 'text/plain; charset=UTF-8']))->name('robots');

Route::get('sitemap.xml', fn () => response(view('sitemap', [
    'urls' => [url('/'), route('register'), route('login')],
])->render(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']))->name('sitemap');
