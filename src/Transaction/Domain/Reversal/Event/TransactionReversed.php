<?php

declare(strict_types=1);

namespace Transaction\Domain\Reversal\Event;

use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Reversal\ValueObject\ReversalReason;

/** Announces that a posted transaction was cancelled by an opposite ledger entry. */
final readonly class TransactionReversed extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        TransactionId $reversalId,
        DateTimeImmutable $occurredOn,
        private LedgerEntryId $originalLedgerEntryId,
        private LedgerEntryId $reversalLedgerEntryId,
        private TransactionReference $reference,
        private ReversalReason $reason,
        ?CorrelationId $correlationId = null
    ) {
        parent::__construct($eventId, $reversalId, 1, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'transaction.reversed';
    }

    /** @return array{original_ledger_entry_id: string, reversal_ledger_entry_id: string, reference: string, reason: string} */
    public function payload(): array
    {
        return [
            'original_ledger_entry_id' => $this->originalLedgerEntryId->value(),
            'reversal_ledger_entry_id' => $this->reversalLedgerEntryId->value(),
            'reference' => $this->reference->value(),
            'reason' => $this->reason->value(),
        ];
    }
}
