<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cron hosting berjalan tiap 6 menit (*/6), jadi semua jadwal memakai menit kelipatan 6. Perintahnya aman diulang (ada penanda terkirim).
Schedule::command('hermes:send-reminders')->cron('*/6 * * * *')->withoutOverlapping();
Schedule::command('hermes:backup')->dailyAt('01:42')->timezone('Asia/Makassar')->withoutOverlapping();
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
Schedule::command('hermes:cek-wa')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('hermes:owner-reports')->cron('*/6 * * * *')->withoutOverlapping();
