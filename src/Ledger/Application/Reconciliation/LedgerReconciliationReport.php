<?php

declare(strict_types=1);

namespace Ledger\Application\Reconciliation;

/** Summarizes integrity problems without exposing financial record details. */
final readonly class LedgerReconciliationReport
{
    public function __construct(
        public int $unbalancedPostedEntries,
        public int $contributionMismatches,
        public int $balanceMismatches,
    ) {}

    public function isReconciled(): bool
    {
        return $this->unbalancedPostedEntries === 0
            && $this->contributionMismatches === 0
            && $this->balanceMismatches === 0;
    }
}
