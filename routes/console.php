<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scan hourly for events entering the 3-day / 24-hour windows. Idempotent, so
// catching up after downtime never double-sends.
Schedule::command('events:send-reminders')->hourly()->withoutOverlapping();
