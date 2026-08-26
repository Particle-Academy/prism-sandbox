<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Reconcile contribution XP daily as a safety net for webhook deliveries that
// were missed or failed. Idempotent (dedup keys) so it can never double-count.
// Requires the scheduler cron (`php artisan schedule:run`) and GITHUB_TOKEN.
Schedule::command('gamification:backfill')->dailyAt('05:00')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
