<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ledger\Application\Reconciliation\LedgerReconciliation;

/** Runs read-only accounting integrity checks for operators and automation. */
final class ReconcileLedgerCommand extends Command
{
    protected $signature = 'banking:reconcile-ledger';

    protected $description = 'Verify posted entries and ledger balance projections';

    public function handle(LedgerReconciliation $reconciliation): int
    {
        $report = $reconciliation->inspect();

        $this->components->twoColumnDetail('Unbalanced posted entries', (string) $report->unbalancedPostedEntries);
        $this->components->twoColumnDetail('Projection contribution mismatches', (string) $report->contributionMismatches);
        $this->components->twoColumnDetail('Ledger balance mismatches', (string) $report->balanceMismatches);

        if (! $report->isReconciled()) {
            $this->newLine();
            $this->error('Ledger reconciliation failed. Preserve evidence and investigate before moving money.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Ledger reconciliation passed. No integrity differences were found.');

        return self::SUCCESS;
    }
}
