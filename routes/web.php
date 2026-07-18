<?php

use App\Docs\DocsRepository;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\Lab\BenchmarkController;
use App\Http\Controllers\Lab\ChatController;
use App\Http\Controllers\Lab\TestSuiteController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\Webhooks\GitHubWebhookController;
use App\Http\Middleware\EnsurePrismLabIsLocal;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (DocsRepository $docs) => Inertia::render('Landing', [
    'version' => $docs->version(),
]))->name('home');

// Community contribution gamification.
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
// GitHub webhook — HMAC-authenticated, CSRF-exempt; contributors tracked from the payload.
Route::post('/webhooks/github', GitHubWebhookController::class)->name('webhooks.github');

if (app()->environment('local')) {
    Route::middleware([EnsurePrismLabIsLocal::class, 'throttle:10,1'])->group(function (): void {
        Route::get('/lab/chat', [ChatController::class, 'show'])->name('lab.chat');
        Route::post('/lab/chat', [ChatController::class, 'run'])->name('lab.chat.run');
        Route::get('/lab/tests', [TestSuiteController::class, 'show'])->name('lab.tests');
        Route::post('/lab/tests', [TestSuiteController::class, 'run'])->name('lab.tests.run');
        Route::get('/lab/benchmarks', [BenchmarkController::class, 'show'])->name('lab.benchmarks');
        Route::get('/lab/benchmarks/export', [BenchmarkController::class, 'export'])->name('lab.benchmarks.export');
    });
}

Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('/docs-index.json', [DocsController::class, 'searchIndex'])->name('docs.search-index');
Route::get('/docs-assets/{path}', [DocsController::class, 'asset'])->where('path', '.*')->name('docs.asset');
Route::get('/sitemap.xml', [DocsController::class, 'sitemap'])->name('sitemap');
Route::get('/llms.txt', [DocsController::class, 'llms'])->name('llms');
Route::get('/docs/{path}', [DocsController::class, 'show'])->where('path', '[A-Za-z0-9\-\/_]+')->name('docs.show');
