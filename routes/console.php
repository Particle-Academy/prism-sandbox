<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Reconcile contribution XP daily as a safety net for webhook deliveries that
// were missed or failed. Idempotent (dedup keys) so it can never double-count.
// Requires the scheduler cron (`php artisan schedule:run`) and GITHUB_TOKEN.
Schedule::command('gamification:backfill')->dailyAt('05:00')->withoutOverlapping();

// The host's nginx gives /robots.txt a 404 status unless a static file exists,
// so fancy-seo's rendered robots.txt is written to public/. SeoTest fails when
// the file drifts from config/fancy-seo.php; run this to bring it back in line.
Artisan::command('seo:robots', function () {
    $response = app(Kernel::class)->handle(Request::create('/robots.txt'));

    if ($response->getStatusCode() !== 200) {
        $this->error('robots.txt route returned '.$response->getStatusCode());

        return 1;
    }

    file_put_contents(public_path('robots.txt'), $response->getContent());
    $this->info('Wrote public/robots.txt');

    return 0;
})->purpose('Write fancy-seo\'s robots.txt to public/robots.txt');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
