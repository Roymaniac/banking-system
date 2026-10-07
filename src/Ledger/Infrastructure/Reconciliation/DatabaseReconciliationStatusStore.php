<?php

declare(strict_types=1);

namespace Ledger\Infrastructure\Reconciliation;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Ledger\Application\Reconciliation\LedgerReconciliationReport;
use Ledger\Application\Reconciliation\ReconciliationStatusStore;

/** Keeps only the latest reconciliation signal so scheduled checks cannot grow storage forever. */
final readonly class DatabaseReconciliationStatusStore implements ReconciliationStatusStore
{
    public function __construct(private ConnectionInterface $connection) {}

    public function record(LedgerReconciliationReport $report, DateTimeImmutable $checkedAt): void
    {
        $this->connection->table('ledger_reconciliation_statuses')->updateOrInsert(
            ['name' => 'ledger'],
            [
                'status' => $report->isReconciled() ? 'healthy' : 'unhealthy',
                'unbalanced_posted_entries' => $report->unbalancedPostedEntries,
                'contribution_mismatches' => $report->contributionMismatches,
                'balance_mismatches' => $report->balanceMismatches,
                'checked_at' => $checkedAt->setTimezone(new DateTimeZone('UTC')),
            ],
        );
    }
}
