<?php

namespace App\Providers;

use App\Parity\SnapshotStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The parity dashboard's snapshot file; tests bind their own path.
        $this->app->singleton(SnapshotStore::class, fn (): SnapshotStore => SnapshotStore::default());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
