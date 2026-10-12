<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Transaction;

use Account\Domain\Account\ValueObject\AccountId;
use Audit\Application\Security\RecordSecurityEvent;
use Audit\Domain\Security\SecurityEventType;
use Audit\Domain\Security\SecuritySeverity;
use Illuminate\Http\Request;
use Transaction\Application\Risk\TransferRiskMonitor;

/** Keeps a privacy-safe investigation record for transfer risk blocks. */
final readonly class AuditTransferRiskMonitor implements TransferRiskMonitor
{
    public function __construct(
        private RecordSecurityEvent $recorder,
        private Request $request,
    ) {}

    public function velocityBlocked(
        AccountId $senderAccountId,
        int $windowMinutes,
        int $maximumTransfers,
    ): void {
        $this->recorder->record(
            SecurityEventType::TransferVelocityBlocked,
            SecuritySeverity::Warning,
            $senderAccountId->value(),
            null,
            $this->request->ip(),
            $this->request->userAgent(),
            [
                'rule' => 'outgoing_transfer_velocity',
                'window_minutes' => $windowMinutes,
                'maximum_transfers' => $maximumTransfers,
            ],
        );
    }
}
