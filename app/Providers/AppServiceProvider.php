<?php

declare(strict_types=1);

namespace App\Providers;

use Account\Application\Closure\AccountClosureBalanceChecker;
use Account\Application\Number\AccountNumberGenerator;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Infrastructure\Number\SecureAccountNumberGenerator;
use Account\Infrastructure\Persistence\DatabaseAccountRepository;
use Administration\Application\Directory\AdministrationDirectoryQuery;
use Administration\Domain\Department\Repository\DepartmentRepository;
use Administration\Domain\Permission\Repository\PermissionRepository;
use Administration\Domain\Permission\Repository\RolePermissionRepository;
use Administration\Domain\Role\Repository\RoleAssignmentRepository;
use Administration\Domain\Role\Repository\RoleRepository;
use Administration\Domain\Staff\Repository\StaffRepository;
use Administration\Infrastructure\Authorization\DatabaseAuthorizationChecker;
use Administration\Infrastructure\Persistence\DatabaseAdministrationDirectoryQuery;
use Administration\Infrastructure\Persistence\DatabaseDepartmentRepository;
use Administration\Infrastructure\Persistence\DatabasePermissionRepository;
use Administration\Infrastructure\Persistence\DatabaseRoleAssignmentRepository;
use Administration\Infrastructure\Persistence\DatabaseRolePermissionRepository;
use Administration\Infrastructure\Persistence\DatabaseRoleRepository;
use Administration\Infrastructure\Persistence\DatabaseStaffRepository;
use Audit\Application\Log\RecordDomainEvent;
use Audit\Application\Query\AuditTrailQuery;
use Audit\Domain\Activity\Repository\ActivityLogRepository;
use Audit\Domain\Log\Repository\AuditLogRepository;
use Audit\Domain\Security\Repository\SecurityEventRepository;
use Audit\Infrastructure\Identity\AuditSecurityMonitor;
use Audit\Infrastructure\Persistence\DatabaseActivityLogRepository;
use Audit\Infrastructure\Persistence\DatabaseAuditLogRepository;
use Audit\Infrastructure\Persistence\DatabaseAuditTrailQuery;
use Audit\Infrastructure\Persistence\DatabaseSecurityEventRepository;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Customer\Infrastructure\Persistence\DatabaseCustomerRepository;
use Identity\Application\Authentication\PasswordHasher;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Application\EmailVerification\EmailVerificationNotifier;
use Identity\Application\EmailVerification\EmailVerificationTokenGenerator;
use Identity\Application\PasswordReset\PasswordResetNotifier;
use Identity\Application\PasswordReset\PasswordResetTokenGenerator;
use Identity\Application\Security\SecurityMonitor;
use Identity\Domain\EmailVerification\Repository\EmailVerificationRequestRepository;
use Identity\Domain\PasswordReset\Repository\PasswordResetRequestRepository;
use Identity\Domain\User\Repository\UserRepository;
use Identity\Infrastructure\Authentication\LaravelPasswordHasher;
use Identity\Infrastructure\EmailVerification\DatabaseEmailVerificationRequestRepository;
use Identity\Infrastructure\EmailVerification\SecureEmailVerificationTokenGenerator;
use Identity\Infrastructure\PasswordReset\DatabasePasswordResetRequestRepository;
use Identity\Infrastructure\PasswordReset\SecurePasswordResetTokenGenerator;
use Identity\Infrastructure\Persistence\DatabaseUserRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Ledger\Application\Balance\ProjectLedgerBalance;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Entry\Event\LedgerEntryPosted;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Infrastructure\Balance\ProjectedAccountClosureBalanceChecker;
use Ledger\Infrastructure\Persistence\DatabaseBalanceProjectionRepository;
use Ledger\Infrastructure\Persistence\DatabaseLedgerEntryRepository;
use Ledger\Infrastructure\Persistence\DatabaseLedgerRepository;
use Notification\Application\Email\EmailSender;
use Notification\Application\Email\EmailTransport;
use Notification\Application\Outbox\EmailOutboxQuery;
use Notification\Application\Outbox\EmailOutboxRetryGateway;
use Notification\Application\Template\EmailTemplateRenderer;
use Notification\Domain\Outbox\Repository\EmailOutboxRepository;
use Notification\Infrastructure\Email\LaravelEmailSender;
use Notification\Infrastructure\Identity\EmailVerificationNotification;
use Notification\Infrastructure\Identity\PasswordResetNotification;
use Notification\Infrastructure\Outbox\DatabaseEmailOutboxQuery;
use Notification\Infrastructure\Outbox\DatabaseEmailOutboxRepository;
use Notification\Infrastructure\Outbox\DatabaseEmailOutboxRetryGateway;
use Notification\Infrastructure\Outbox\OutboxEmailSender;
use Notification\Infrastructure\Template\BladeEmailTemplateRenderer;
use Reporting\Application\Customer\CustomerReportQuery;
use Reporting\Application\Ledger\LedgerReportQuery;
use Reporting\Application\Transaction\TransactionReportQuery;
use Reporting\Infrastructure\Persistence\DatabaseCustomerReportQuery;
use Reporting\Infrastructure\Persistence\DatabaseLedgerReportQuery;
use Reporting\Infrastructure\Persistence\DatabaseTransactionReportQuery;
use Shared\Application\Health\SystemHealthCheck;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\UuidGenerator;
use Shared\Infrastructure\Clock\SystemClock;
use Shared\Infrastructure\Event\LaravelEventPublisher;
use Shared\Infrastructure\Health\DatabaseSystemHealthCheck;
use Shared\Infrastructure\Identifier\NativeUuidGenerator;
use Shared\Infrastructure\Persistence\LaravelTransactionManager;
use Transaction\Domain\DailyLimit\Repository\DailyTransactionLimitRepository;
use Transaction\Domain\Deposit\Repository\DepositRepository;
use Transaction\Domain\MultipleTransfer\Repository\MultipleTransferRepository;
use Transaction\Domain\Reversal\Repository\ReversalRepository;
use Transaction\Domain\Transfer\Repository\TransferRepository;
use Transaction\Domain\Withdrawal\Repository\WithdrawalRepository;
use Transaction\Infrastructure\Persistence\DatabaseDailyTransactionLimitRepository;
use Transaction\Infrastructure\Persistence\DatabaseDepositRepository;
use Transaction\Infrastructure\Persistence\DatabaseMultipleTransferRepository;
use Transaction\Infrastructure\Persistence\DatabaseReversalRepository;
use Transaction\Infrastructure\Persistence\DatabaseTransferRepository;
use Transaction\Infrastructure\Persistence\DatabaseWithdrawalRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SystemHealthCheck::class, DatabaseSystemHealthCheck::class);
        $this->app->singleton(AdministrationDirectoryQuery::class, DatabaseAdministrationDirectoryQuery::class);
        $this->app->singleton(DepartmentRepository::class, DatabaseDepartmentRepository::class);
        $this->app->singleton(StaffRepository::class, DatabaseStaffRepository::class);
        $this->app->singleton(RoleRepository::class, DatabaseRoleRepository::class);
        $this->app->singleton(RoleAssignmentRepository::class, DatabaseRoleAssignmentRepository::class);
        $this->app->singleton(PermissionRepository::class, DatabasePermissionRepository::class);
        $this->app->singleton(RolePermissionRepository::class, DatabaseRolePermissionRepository::class);
        $this->app->singleton(ActivityLogRepository::class, DatabaseActivityLogRepository::class);
        $this->app->singleton(AuditTrailQuery::class, DatabaseAuditTrailQuery::class);
        $this->app->singleton(AuditLogRepository::class, DatabaseAuditLogRepository::class);
        $this->app->singleton(SecurityEventRepository::class, DatabaseSecurityEventRepository::class);
        $this->app->singleton(SecurityMonitor::class, AuditSecurityMonitor::class);
        $this->app->singleton(CustomerReportQuery::class, DatabaseCustomerReportQuery::class);
        $this->app->singleton(LedgerReportQuery::class, DatabaseLedgerReportQuery::class);
        $this->app->singleton(TransactionReportQuery::class, DatabaseTransactionReportQuery::class);
        $this->app->singleton(EmailSender::class, OutboxEmailSender::class);
        $this->app->singleton(EmailTransport::class, LaravelEmailSender::class);
        $this->app->singleton(EmailOutboxRepository::class, DatabaseEmailOutboxRepository::class);
        $this->app->singleton(EmailOutboxQuery::class, DatabaseEmailOutboxQuery::class);
        $this->app->singleton(EmailOutboxRetryGateway::class, DatabaseEmailOutboxRetryGateway::class);
        $this->app->singleton(EmailTemplateRenderer::class, BladeEmailTemplateRenderer::class);
        $this->app->singleton(EmailVerificationNotifier::class, EmailVerificationNotification::class);
        $this->app->singleton(PasswordResetNotifier::class, PasswordResetNotification::class);
        $this->app->singleton(DepositRepository::class, DatabaseDepositRepository::class);
        $this->app->singleton(DailyTransactionLimitRepository::class, DatabaseDailyTransactionLimitRepository::class);
        $this->app->singleton(MultipleTransferRepository::class, DatabaseMultipleTransferRepository::class);
        $this->app->singleton(ReversalRepository::class, DatabaseReversalRepository::class);
        $this->app->singleton(TransferRepository::class, DatabaseTransferRepository::class);
        $this->app->singleton(WithdrawalRepository::class, DatabaseWithdrawalRepository::class);
        $this->app->singleton(AccountClosureBalanceChecker::class, ProjectedAccountClosureBalanceChecker::class);
        $this->app->singleton(BalanceProjectionRepository::class, DatabaseBalanceProjectionRepository::class);
        $this->app->singleton(LedgerEntryRepository::class, DatabaseLedgerEntryRepository::class);
        $this->app->singleton(LedgerRepository::class, DatabaseLedgerRepository::class);
        $this->app->singleton(AccountNumberGenerator::class, SecureAccountNumberGenerator::class);
        $this->app->singleton(AccountRepository::class, DatabaseAccountRepository::class);
        $this->app->singleton(CustomerRepository::class, DatabaseCustomerRepository::class);
        $this->app->singleton(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->singleton(UserRepository::class, DatabaseUserRepository::class);
        $this->app->singleton(AuthorizationChecker::class, DatabaseAuthorizationChecker::class);
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
        // Balance projection is a reaction to a committed posted-entry event.
        Event::listen(LedgerEntryPosted::class, ProjectLedgerBalance::class);

        // Laravel's wildcard listener lets one audit recorder cover every domain event.
        Event::listen('*', function (string $eventName, array $payload): void {
            $event = $payload[0] ?? null;

            if ($event instanceof DomainEvent) {
                $this->app->make(RecordDomainEvent::class)->handle($event);
            }
        });
    }
}
