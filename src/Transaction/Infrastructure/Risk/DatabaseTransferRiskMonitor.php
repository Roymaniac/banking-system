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
use Transaction\Application\Risk\TransferRiskCase;
use Transaction\Application\Risk\TransferRiskCaseNotifier;
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
        private TransferRiskCaseNotifier $notifier,
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
            $reviewMinutes = max(1, (int) config('security.transfer_risk.review_minutes'));
            $dueAt = $detectedAt->add(new \DateInterval("PT{$reviewMinutes}M"));
            $openKey = hash('sha256', $senderAccountId->value().'|'.self::RULE);

            // Repeated blocks update the audit trail but share one open case,
            // keeping the reviewer queue useful during a burst of retries.
            $caseId = $this->uuidGenerator->generate();
            $inserted = $this->connection->table('transfer_risk_cases')->insertOrIgnore([
                'id' => $caseId->value(),
                'account_id' => $senderAccountId->value(),
                'rule' => self::RULE,
                'status' => 'pending',
                'window_minutes' => $windowMinutes,
                'maximum_transfers' => $maximumTransfers,
                'detected_at' => $detectedAt,
                'due_at' => $dueAt,
                'open_key' => $openKey,
                'version' => 1,
            ]);

            if ($inserted === 1) {
                $number = $this->connection->table('accounts')
                    ->where('id', $senderAccountId->value())
                    ->value('number');
                $this->notifier->opened(new TransferRiskCase(
                    $caseId,
                    $number === null ? '----' : substr((string) $number, -4),
                    self::RULE,
                    'pending',
                    $windowMinutes,
                    $maximumTransfers,
                    $detectedAt,
                    $dueAt,
                ));
            }

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
