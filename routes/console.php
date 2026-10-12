<?php

use App\Support\Idempotency\PruneExpiredIdempotencyRecords;
use App\Support\Security\PruneExpiredAuthenticationRecords;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Notification\Infrastructure\Outbox\ProcessEmailOutboxJob;
use Shared\Infrastructure\Health\RecordSchedulerHeartbeat;
use Transaction\Application\Control\MoneyMovementResumeReminder;
use Transaction\Application\Control\MoneyMovementResumeRequestExpiry;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The scheduler only queues a lightweight worker; encrypted email contents
// remain in the outbox until that worker confirms successful delivery.
Schedule::job(new ProcessEmailOutboxJob)
    ->everyMinute()
    ->withoutOverlapping();

// This direct heartbeat proves the scheduler itself ran, independently of
// whether a queued job worker is currently available.
Schedule::call(fn () => app(RecordSchedulerHeartbeat::class)->record())
    ->name('scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

// Reconciliation is read-only for financial records and refreshes the
// protected readiness signal after comparing the journal and projections.
Schedule::command('banking:reconcile-ledger')
    ->name('ledger-reconciliation')
    ->hourly()
    ->withoutOverlapping();

// Expired requests must disappear from the live approval queue promptly.
Schedule::call(fn () => app(MoneyMovementResumeRequestExpiry::class)->expire())
    ->name('expire-money-movement-resume-requests')
    ->everyMinute()
    ->withoutOverlapping();

// A single reminder gives reviewers time to act without flooding inboxes.
Schedule::call(fn () => app(MoneyMovementResumeReminder::class)->sendDue())
    ->name('remind-money-movement-resume-requests')
    ->everyMinute()
    ->withoutOverlapping();

// Retry responses expire automatically so the safety table stays bounded.
Schedule::call(fn () => app(PruneExpiredIdempotencyRecords::class)->prune())
    ->name('prune-api-idempotency-records')
    ->hourly()
    ->withoutOverlapping();

// Expired MFA challenges and access tokens are deleted after their expiry
// window has passed, while active and non-expiring sessions remain untouched.
Schedule::call(fn () => app(PruneExpiredAuthenticationRecords::class)->prune())
    ->name('prune-expired-authentication-records')
    ->hourly()
    ->withoutOverlapping();
