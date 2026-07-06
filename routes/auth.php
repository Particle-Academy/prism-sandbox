<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
|
| Login / register / password-reset routes are provided by Laravel Fortify
| (see app/Providers/FortifyServiceProvider.php + config/fortify.php). The
| routes below are the app's own authenticated surfaces.
|
*/

Route::middleware('auth')->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', fn () => Inertia::render('settings/Profile'))->name('settings.profile');
    Route::get('settings/password', fn () => Inertia::render('settings/Password'))->name('settings.password');
});
