<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Infrastructure\Clock\SystemClock;
use Shared\Infrastructure\Event\LaravelEventPublisher;
use Shared\Infrastructure\Persistence\LaravelTransactionManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(EventPublisher::class, LaravelEventPublisher::class);
        $this->app->singleton(TransactionManager::class, LaravelTransactionManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
