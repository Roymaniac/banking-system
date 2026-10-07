<?php

declare(strict_types=1);

namespace Ledger\Application\Reconciliation;

use Shared\Contracts\Clock;

/** Runs reconciliation and records its safe operational status. */
final readonly class RunLedgerReconciliation
{
    public function __construct(
        private LedgerReconciliation $reconciliation,
        private ReconciliationStatusStore $statuses,
        private Clock $clock,
    ) {}

    public function handle(): LedgerReconciliationReport
    {
        $report = $this->reconciliation->inspect();
        $this->statuses->record($report, $this->clock->now());

        return $report;
    }
}
