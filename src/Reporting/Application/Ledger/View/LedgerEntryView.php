<?php

declare(strict_types=1);

namespace Reporting\Application\Ledger\View;

use DateTimeImmutable;

/** A complete posted journal entry and all of its accounting legs. */
final readonly class LedgerEntryView
{
    /** @param list<LedgerPostingView> $postings */
    public function __construct(
        public string $id,
        public string $reference,
        public string $description,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $recordedAt,
        public int $totalDebitMinorUnits,
        public int $totalCreditMinorUnits,
        public array $postings,
    ) {}

    public function isBalanced(): bool
    {
        return $this->totalDebitMinorUnits === $this->totalCreditMinorUnits;
    }
}
