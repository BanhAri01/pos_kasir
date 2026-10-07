<?php

use Illuminate\Support\Facades\Route;

// Route tiap fitur ada di app/Modules/*/routes/web.php (dimuat oleh ModuleServiceProvider).

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));
