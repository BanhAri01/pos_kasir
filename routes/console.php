<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pengingat WhatsApp otomatis (janji temu & paket member). Di server: jalankan `php artisan schedule:run` tiap menit (cron).
Illuminate\Support\Facades\Schedule::command('hermes:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
