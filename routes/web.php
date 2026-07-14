<?php

use App\Docs\DocsRepository;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\Webhooks\GitHubWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (DocsRepository $docs) => Inertia::render('Landing', [
    'version' => $docs->version(),
]))->name('home');

// Community contribution gamification.
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
// GitHub webhook — HMAC-authenticated, CSRF-exempt; contributors tracked from the payload.
Route::post('/webhooks/github', GitHubWebhookController::class)->name('webhooks.github');

Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('/docs-index.json', [DocsController::class, 'searchIndex'])->name('docs.search-index');
Route::get('/docs-assets/{path}', [DocsController::class, 'asset'])->where('path', '.*')->name('docs.asset');
Route::get('/sitemap.xml', [DocsController::class, 'sitemap'])->name('sitemap');
Route::get('/llms.txt', [DocsController::class, 'llms'])->name('llms');
Route::get('/docs/{path}', [DocsController::class, 'show'])->where('path', '[A-Za-z0-9\-\/_]+')->name('docs.show');
