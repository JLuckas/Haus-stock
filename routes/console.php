<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Also runs on page loads; this keeps it going on days nobody opens the app (needs `schedule:run` every minute).
Schedule::command('expenses:recurring')->dailyAt('00:05');
