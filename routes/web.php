<?php

use App\Docs\DocsRepository;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\Webhooks\GitHubWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (DocsRepository $docs) => Inertia::render('Landing', [
    'version' => $docs->version(),
]))->name('home');

// What the ecosystem does, by feature, and every package it publishes.
Route::get('/features', [FeaturesController::class, 'index'])->name('features.index');
Route::get('/features/{slug}', [FeaturesController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('features.show');
Route::get('/packages', [FeaturesController::class, 'packages'])->name('packages');

// Community contribution gamification.
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
// GitHub webhook — HMAC-authenticated, CSRF-exempt; contributors tracked from the payload.
Route::post('/webhooks/github', GitHubWebhookController::class)->name('webhooks.github');

// A small public demonstration, on Groq's free tier. The Lab that used to sit
// here — sixteen provider credentials, real spend, live generations — now
// lives in prism-labs, which is never deployed.
//
// Per-client throttle plus a database-backed provider-wide request budget in
// the controller: multiple clients cannot individually spend the shared quota.
Route::get('/demo', [DemoController::class, 'show'])->name('demo');
Route::post('/demo', [DemoController::class, 'run'])->middleware('throttle:5,1')->name('demo.run');

Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('/docs-index.json', [DocsController::class, 'searchIndex'])->name('docs.search-index');
Route::get('/docs-assets/{path}', [DocsController::class, 'asset'])->where('path', '.*')->name('docs.asset');
Route::get('/docs/{path}', [DocsController::class, 'show'])->where('path', '[A-Za-z0-9\-\/_]+')->name('docs.show');
