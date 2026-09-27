<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Identity;

use Audit\Application\Security\RecordSecurityEvent;
use Audit\Domain\Security\SecurityEventType;
use Audit\Domain\Security\SecuritySeverity;
use Identity\Application\Security\SecurityMonitor;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Http\Request;

/** Translates Identity outcomes into safe security audit records. */
final readonly class AuditSecurityMonitor implements SecurityMonitor
{
    public function __construct(
        private RecordSecurityEvent $recorder,
        private Request $request,
    ) {}

    public function loginSucceeded(UserId $userId): void
    {
        $this->record(
            SecurityEventType::LoginSucceeded,
            SecuritySeverity::Information,
            $userId->value(),
        );
    }

    public function loginFailed(string $email): void
    {
        // A fingerprint groups repeated attempts without retaining the email address.
        $fingerprint = hash('sha256', mb_strtolower(trim($email)));

        $this->record(
            SecurityEventType::LoginFailed,
            SecuritySeverity::Warning,
            null,
            $fingerprint,
        );
    }

    public function unverifiedLoginBlocked(UserId $userId): void
    {
        $this->record(
            SecurityEventType::UnverifiedLoginBlocked,
            SecuritySeverity::Warning,
            $userId->value(),
        );
    }

    public function accessDenied(UserId $userId, Permission $permission): void
    {
        $this->record(
            SecurityEventType::AccessDenied,
            SecuritySeverity::Warning,
            $userId->value(),
            null,
            ['permission' => $permission->value()],
        );
    }

    /** @param array<string, bool|int|float|string|null> $details */
    private function record(
        SecurityEventType $type,
        SecuritySeverity $severity,
        ?string $subjectId,
        ?string $subjectFingerprint = null,
        array $details = [],
    ): void {
        $this->recorder->record(
            $type,
            $severity,
            $subjectId,
            $subjectFingerprint,
            $this->request->ip(),
            $this->request->userAgent(),
            $details,
        );
    }
}
