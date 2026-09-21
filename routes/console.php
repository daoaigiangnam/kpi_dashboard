<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display the inspiring quote');

Schedule::command('services:monitor')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

// Network/FTTH checks run every minute. Each service has its own monitor interval,
// so the command skips services that are not due yet.
Schedule::command('services:monitor-network')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Alert escalation must run every minute so configured delays are honored closely.
Schedule::command('services:alert-emails')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('it-tools:expiry-alert --days=30')
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->onOneServer();
