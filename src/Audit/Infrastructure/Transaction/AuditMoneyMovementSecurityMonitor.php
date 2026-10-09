<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Transaction;

use Audit\Application\Security\RecordSecurityEvent;
use Audit\Domain\Security\SecurityEventType;
use Audit\Domain\Security\SecuritySeverity;
use Identity\Domain\User\ValueObject\UserId;
use Transaction\Application\Control\MoneyMovementSecurityMonitor;

/** Stores emergency money-movement actions in the permanent security trail. */
final readonly class AuditMoneyMovementSecurityMonitor implements MoneyMovementSecurityMonitor
{
    public function __construct(private RecordSecurityEvent $recorder) {}

    public function breakGlassResumeUsed(UserId $operatorId, string $incidentReference): void
    {
        $this->recorder->record(
            SecurityEventType::MoneyMovementBreakGlassResumed,
            SecuritySeverity::Critical,
            $operatorId->value(),
            null,
            null,
            null,
            [
                'incident_reference' => $incidentReference,
                'channel' => 'cli',
            ],
        );
    }
}
