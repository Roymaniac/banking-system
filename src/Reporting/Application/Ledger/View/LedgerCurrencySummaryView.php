<?php

declare(strict_types=1);

namespace Reporting\Application\Ledger\View;

/** Period debit and credit totals for one currency. */
final readonly class LedgerCurrencySummaryView
{
    public function __construct(
        public string $currency,
        public int $totalDebitMinorUnits,
        public int $totalCreditMinorUnits,
    ) {}

    public function differenceMinorUnits(): int
    {
        return $this->totalCreditMinorUnits - $this->totalDebitMinorUnits;
    }

    public function isBalanced(): bool
    {
        return $this->differenceMinorUnits() === 0;
    }
}
