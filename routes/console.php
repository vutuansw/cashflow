<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cron trên server: * * * * * php artisan schedule:run (plan/07 mục 4).
Schedule::command('cashflow:prune-bills')->dailyAt('03:10');
Schedule::command('cashflow:purge')->dailyAt('03:30');
