<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler (GoDaddy cPanel: one cron job "* * * * * php artisan
| schedule:run" drives everything below - see docs/deployment.md)
|--------------------------------------------------------------------------
*/

// Process queued jobs (bill generation, bulk PDFs, notifications, ...).
// max-time kept under 60s so it never overlaps the next minute's cron tick.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('bills:mark-overdue')->dailyAt('01:00');
