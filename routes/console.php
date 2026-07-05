<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Remove Sanctum personal access tokens that have passed their expires_at.
// Tokens created without an expiry (remember-me tokens that never expire) are unaffected.
Schedule::command('sanctum:prune-expired --hours=0')->daily();
Schedule::command('preschool:automation-daily-checks')->dailyAt('02:15');
