<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Event;

use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that a financial entry has begun but is not yet posted. */
final readonly class LedgerEntryDrafted extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        LedgerEntryId $entryId,
        DateTimeImmutable $occurredOn,
        private LedgerId $ledgerId,
        private EntryReference $reference,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $entryId, 1, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'ledger.entry.drafted';
    }

    /** @return array{ledger_id: string, reference: string} */
    public function payload(): array
    {
        // The description is deliberately excluded because free text may
        // contain customer information that should not spread through events.
        return [
            'ledger_id' => $this->ledgerId->value(),
            'reference' => $this->reference->value(),
        ];
    }
}
