<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Risk;

use Account\Domain\Account\ValueObject\AccountId;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Transaction\Application\Risk\TransferRiskAssessor;
use Transaction\Domain\Risk\Exception\SuspiciousTransferVelocity;

/** Uses completed transfers to detect an unusual burst of outgoing requests. */
final readonly class DatabaseTransferRiskAssessor implements TransferRiskAssessor
{
    public function __construct(private ConnectionInterface $connection) {}

    public function assertAllowed(AccountId $senderAccountId, DateTimeImmutable $assessedAt): void
    {
        $windowMinutes = max(1, (int) config('security.transfer_risk.velocity_window_minutes'));
        $maximumTransfers = max(1, (int) config('security.transfer_risk.maximum_transfers'));
        $windowStartedAt = $assessedAt
            ->sub(new DateInterval("PT{$windowMinutes}M"))
            ->setTimezone(new DateTimeZone('UTC'));

        // Single and batch transfers both represent one customer instruction.
        // Counting both closes the gap where alternating endpoints could evade
        // an otherwise sound velocity limit.
        $recentTransfers = $this->connection->table('transfers')
            ->where('sender_account_id', $senderAccountId->value())
            ->where('completed_at', '>=', $windowStartedAt)
            ->count();
        $recentBatches = $this->connection->table('multiple_transfers')
            ->where('sender_account_id', $senderAccountId->value())
            ->where('completed_at', '>=', $windowStartedAt)
            ->count();

        if ($recentTransfers + $recentBatches >= $maximumTransfers) {
            throw SuspiciousTransferVelocity::create($windowMinutes, $maximumTransfers);
        }
    }
}
