<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Event;

use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that a balanced entry became permanent and balance-affecting. */
final readonly class LedgerEntryPosted extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        LedgerEntryId $entryId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        private int $postingCount,
        private int $totalMinorUnits,
        private LedgerCurrency $currency,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $entryId, $aggregateVersion, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'ledger.entry.posted';
    }

    public function totalMinorUnits(): int
    {
        return $this->totalMinorUnits;
    }

    /** @return array{posting_count: int, currency: string} */
    public function payload(): array
    {
        return [
            'posting_count' => $this->postingCount,
            'currency' => $this->currency->value(),
        ];
    }
}
