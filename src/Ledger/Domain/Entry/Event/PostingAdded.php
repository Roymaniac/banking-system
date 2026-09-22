<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Event;

use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that a debit or credit line was added to a draft entry. */
final readonly class PostingAdded extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        LedgerEntryId $entryId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        private PostingId $postingId,
        private LedgerId $ledgerId,
        private PostingSide $side,
        private PostingAmount $amount,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $entryId, $aggregateVersion, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'ledger.posting.added';
    }

    public function amount(): PostingAmount
    {
        return $this->amount;
    }

    /** @return array{posting_id: string, ledger_id: string, side: string, currency: string} */
    public function payload(): array
    {
        // Exact amounts remain on the event object for trusted handlers but
        // are excluded from generic serialized payloads and logs.
        return [
            'posting_id' => $this->postingId->value(),
            'ledger_id' => $this->ledgerId->value(),
            'side' => $this->side->value,
            'currency' => $this->amount->currency()->value(),
        ];
    }
}
