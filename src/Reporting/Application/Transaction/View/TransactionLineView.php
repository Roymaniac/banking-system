<?php

declare(strict_types=1);

namespace Reporting\Application\Transaction\View;

use DateTimeImmutable;

/** One posted debit or credit displayed on an account transaction report. */
final readonly class TransactionLineView
{
    public function __construct(
        public string $ledgerEntryId,
        public string $reference,
        public string $description,
        public string $transactionType,
        public string $side,
        public int $minorUnits,
        public int $balanceAfterMinorUnits,
        public DateTimeImmutable $occurredAt,
    ) {}
}
