<?php

declare(strict_types=1);

namespace Ledger\Application\Reconciliation;

interface LedgerReconciliation
{
    public function inspect(): LedgerReconciliationReport;
}
