<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notify:birthdays')->dailyAt('09:00');
Schedule::command('app:backup-tenant-data')->dailyAt('00:00');
Schedule::command('maintenance:check-health')->everyTenMinutes();
Schedule::command('trips:analyze-stops')->dailyAt('03:00');
Schedule::command('reports:calculate-daily')->dailyAt('04:00');
