<?php

declare(strict_types=1);

namespace Ledger\Application\Reconciliation;

use DateTimeImmutable;

interface ReconciliationStatusStore
{
    public function record(LedgerReconciliationReport $report, DateTimeImmutable $checkedAt): void;
}
