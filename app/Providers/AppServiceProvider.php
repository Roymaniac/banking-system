<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Shared\Contracts\Clock;
use Shared\Infrastructure\Clock\SystemClock;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
