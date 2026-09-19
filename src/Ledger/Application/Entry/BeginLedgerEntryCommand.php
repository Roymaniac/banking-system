<?php

declare(strict_types=1);

namespace Ledger\Application\Entry;

use DateTimeImmutable;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries the source reference and description for a new draft entry. */
final readonly class BeginLedgerEntryCommand
{
    public function __construct(
        public LedgerId $ledgerId,
        public string $reference,
        public string $description,
        public DateTimeImmutable $occurredAt,
        public ?CorrelationId $correlationId = null,
    ) {}
}
