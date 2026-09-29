<?php

declare(strict_types=1);

namespace Reporting\Application\Transaction\View;

use Reporting\Application\Transaction\TransactionReportPeriod;

/** A read-only account statement with period totals and running balances. */
final readonly class TransactionReportView
{
    /** @param list<TransactionLineView> $transactions */
    public function __construct(
        public string $accountId,
        public ?string $accountNumber,
        public string $currency,
        public TransactionReportPeriod $period,
        public int $openingBalanceMinorUnits,
        public int $totalDebitMinorUnits,
        public int $totalCreditMinorUnits,
        public int $closingBalanceMinorUnits,
        public int $totalTransactions,
        public array $transactions,
    ) {}

    public function transactionCount(): int
    {
        return count($this->transactions);
    }

    public function hasNextPage(): bool
    {
        return $this->period->page * $this->period->perPage < $this->totalTransactions;
    }
}
