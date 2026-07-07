<?php

use App\Docs\DocsRepository;
use App\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (DocsRepository $docs) => Inertia::render('Landing', [
    'version' => $docs->version(),
]))->name('home');

Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('/docs-index.json', [DocsController::class, 'searchIndex'])->name('docs.search-index');
Route::get('/docs-assets/{path}', [DocsController::class, 'asset'])->where('path', '.*')->name('docs.asset');
Route::get('/sitemap.xml', [DocsController::class, 'sitemap'])->name('sitemap');
Route::get('/llms.txt', [DocsController::class, 'llms'])->name('llms');
Route::get('/docs/{path}', [DocsController::class, 'show'])->where('path', '[A-Za-z0-9\-\/_]+')->name('docs.show');

Route::get('/demo/hitl', [App\Http\Controllers\HitlDemoController::class, 'show'])->name('demo.hitl');
Route::post('/demo/hitl/run', [App\Http\Controllers\HitlDemoController::class, 'run'])->name('demo.hitl.run');
Route::post('/demo/hitl/resume', [App\Http\Controllers\HitlDemoController::class, 'resume'])->name('demo.hitl.resume');
