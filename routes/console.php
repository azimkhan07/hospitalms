<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with the command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly platform snapshot with built-in retention (PLAN.md section 16):
// hms:backup keeps the newest hms.backup_keep_days files per run.
Schedule::command('hms:backup')
    ->dailyAt('01:30')
    ->withoutOverlapping();

// OPD hygiene: auto-terminate appointments nobody started after 3 days.
Schedule::command('hms:purge-stale-appointments')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// Reminder sweep: ping staff (and try the patient) before upcoming slots.
Schedule::command('hms:send-appointment-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
