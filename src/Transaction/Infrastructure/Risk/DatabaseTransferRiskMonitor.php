<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Risk;

use Account\Domain\Account\ValueObject\AccountId;
use Audit\Application\Security\RecordSecurityEvent;
use Audit\Domain\Security\SecurityEventType;
use Audit\Domain\Security\SecuritySeverity;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\UuidGenerator;
use Transaction\Application\Risk\TransferRiskMonitor;

/** Opens a review case and records every blocked attempt in the security trail. */
final readonly class DatabaseTransferRiskMonitor implements TransferRiskMonitor
{
    private const RULE = 'outgoing_transfer_velocity';

    public function __construct(
        private ConnectionInterface $connection,
        private UuidGenerator $uuidGenerator,
        private Clock $clock,
        private RecordSecurityEvent $recorder,
        private Request $request,
    ) {}

    public function velocityBlocked(
        AccountId $senderAccountId,
        int $windowMinutes,
        int $maximumTransfers,
    ): void {
        $this->connection->transaction(function () use (
            $senderAccountId,
            $windowMinutes,
            $maximumTransfers,
        ): void {
            $detectedAt = $this->clock->now();
            $openKey = hash('sha256', $senderAccountId->value().'|'.self::RULE);

            // Repeated blocks update the audit trail but share one open case,
            // keeping the reviewer queue useful during a burst of retries.
            $this->connection->table('transfer_risk_cases')->insertOrIgnore([
                'id' => $this->uuidGenerator->generate()->value(),
                'account_id' => $senderAccountId->value(),
                'rule' => self::RULE,
                'status' => 'pending',
                'window_minutes' => $windowMinutes,
                'maximum_transfers' => $maximumTransfers,
                'detected_at' => $detectedAt,
                'open_key' => $openKey,
                'version' => 1,
            ]);

            $this->recorder->record(
                SecurityEventType::TransferVelocityBlocked,
                SecuritySeverity::Warning,
                $senderAccountId->value(),
                null,
                $this->request->ip(),
                $this->request->userAgent(),
                [
                    'rule' => self::RULE,
                    'window_minutes' => $windowMinutes,
                    'maximum_transfers' => $maximumTransfers,
                ],
            );
        });
    }
}
