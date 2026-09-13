<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('services:monitor')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

// Alert escalation must run every minute so configured delays (for example 1/2/3 minutes)
// are honored closely. This command only sends levels whose delay has elapsed and
// ServiceAlertEmailService prevents duplicate sends.
Schedule::command('services:alert-emails')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('it-tools:expiry-alert --days=30')
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->onOneServer();
