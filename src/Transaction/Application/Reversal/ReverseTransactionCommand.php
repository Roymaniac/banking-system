<?php

declare(strict_types=1);

namespace Transaction\Application\Reversal;

use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries the posted ledger entry that must be cancelled by an opposite entry. */
final readonly class ReverseTransactionCommand
{
    public function __construct(
        public LedgerEntryId $originalLedgerEntryId,
        public string $reference,
        public string $reason,
        public DateTimeImmutable $occurredAt,
        public ?CorrelationId $correlationId = null,
    ) {}
}
