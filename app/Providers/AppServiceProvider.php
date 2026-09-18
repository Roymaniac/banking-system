<?php

declare(strict_types=1);

namespace App\Providers;

use Account\Application\Number\AccountNumberGenerator;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Infrastructure\Number\SecureAccountNumberGenerator;
use Account\Infrastructure\Persistence\DatabaseAccountRepository;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Customer\Infrastructure\Persistence\DatabaseCustomerRepository;
use Identity\Application\Authentication\PasswordHasher;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Application\EmailVerification\EmailVerificationTokenGenerator;
use Identity\Application\PasswordReset\PasswordResetTokenGenerator;
use Identity\Domain\EmailVerification\Repository\EmailVerificationRequestRepository;
use Identity\Domain\PasswordReset\Repository\PasswordResetRequestRepository;
use Identity\Infrastructure\Authentication\LaravelPasswordHasher;
use Identity\Infrastructure\Authorization\LaravelGateAuthorizationChecker;
use Identity\Infrastructure\EmailVerification\DatabaseEmailVerificationRequestRepository;
use Identity\Infrastructure\EmailVerification\SecureEmailVerificationTokenGenerator;
use Identity\Infrastructure\PasswordReset\DatabasePasswordResetRequestRepository;
use Identity\Infrastructure\PasswordReset\SecurePasswordResetTokenGenerator;
use Illuminate\Support\ServiceProvider;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;
use Shared\Infrastructure\Clock\SystemClock;
use Shared\Infrastructure\Event\LaravelEventPublisher;
use Shared\Infrastructure\Identifier\NativeUuidGenerator;
use Shared\Infrastructure\Persistence\LaravelTransactionManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccountNumberGenerator::class, SecureAccountNumberGenerator::class);
        $this->app->singleton(AccountRepository::class, DatabaseAccountRepository::class);
        $this->app->singleton(CustomerRepository::class, DatabaseCustomerRepository::class);
        $this->app->singleton(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->singleton(AuthorizationChecker::class, LaravelGateAuthorizationChecker::class);
        $this->app->singleton(
            EmailVerificationTokenGenerator::class,
            SecureEmailVerificationTokenGenerator::class,
        );
        $this->app->singleton(
            EmailVerificationRequestRepository::class,
            DatabaseEmailVerificationRequestRepository::class,
        );
        $this->app->singleton(PasswordResetTokenGenerator::class, SecurePasswordResetTokenGenerator::class);
        $this->app->singleton(
            PasswordResetRequestRepository::class,
            DatabasePasswordResetRequestRepository::class,
        );
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(EventPublisher::class, LaravelEventPublisher::class);
        $this->app->singleton(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->singleton(UuidGenerator::class, NativeUuidGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
