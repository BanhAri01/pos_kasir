<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cron hosting berjalan tiap 6 menit (*/6), jadi semua jadwal memakai menit kelipatan 6. Perintahnya aman diulang (ada penanda terkirim).
Illuminate\Support\Facades\Schedule::command('hermes:send-reminders')->cron('*/6 * * * *')->withoutOverlapping();
Illuminate\Support\Facades\Schedule::command('hermes:backup')->dailyAt('01:42')->timezone('Asia/Makassar')->withoutOverlapping();
Illuminate\Support\Facades\Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
Illuminate\Support\Facades\Schedule::command('hermes:owner-reports')->cron('*/6 * * * *')->withoutOverlapping();
