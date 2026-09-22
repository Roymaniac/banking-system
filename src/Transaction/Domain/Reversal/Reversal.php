<?php

declare(strict_types=1);

namespace Transaction\Domain\Reversal;

use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Reversal\Event\TransactionReversed;
use Transaction\Domain\Reversal\ValueObject\ReversalReason;

/** Records the new ledger entry that financially cancels an earlier one. */
final class Reversal extends AggregateRoot
{
    private function __construct(
        private readonly TransactionId $id,
        private readonly LedgerEntryId $originalLedgerEntryId,
        private readonly LedgerEntryId $reversalLedgerEntryId,
        private readonly TransactionReference $reference,
        private readonly ReversalReason $reason,
        private readonly DateTimeImmutable $completedAt,
    ) {}

    public static function complete(
        TransactionId $id,
        LedgerEntryId $originalLedgerEntryId,
        LedgerEntryId $reversalLedgerEntryId,
        TransactionReference $reference,
        ReversalReason $reason,
        DateTimeImmutable $completedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): self {

        $reversal = new self(
            $id,
            $originalLedgerEntryId,
            $reversalLedgerEntryId,
            $reference,
            $reason,
            $completedAt
        );

        $reversal->record(
            new TransactionReversed(
                $eventId,
                $id,
                $completedAt,
                $originalLedgerEntryId,
                $reversalLedgerEntryId,
                $reference,
                $reason,
                $correlationId
            )
        );

        return $reversal;
    }

    /** Rebuilds a stored reversal without announcing another reversal. */
    public static function reconstitute(
        TransactionId $id,
        LedgerEntryId $originalLedgerEntryId,
        LedgerEntryId $reversalLedgerEntryId,
        TransactionReference $reference,
        ReversalReason $reason,
        DateTimeImmutable $completedAt,
        int $version
    ): self {

        $reversal = new self(
            $id,
            $originalLedgerEntryId,
            $reversalLedgerEntryId,
            $reference,
            $reason,
            $completedAt
        );

        $reversal->reconstituteAtVersion($version);

        return $reversal;
    }

    public function id(): TransactionId
    {
        return $this->id;
    }

    public function originalLedgerEntryId(): LedgerEntryId
    {
        return $this->originalLedgerEntryId;
    }

    public function reversalLedgerEntryId(): LedgerEntryId
    {
        return $this->reversalLedgerEntryId;
    }

    public function reference(): TransactionReference
    {
        return $this->reference;
    }

    public function reason(): ReversalReason
    {
        return $this->reason;
    }

    public function completedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }
}
