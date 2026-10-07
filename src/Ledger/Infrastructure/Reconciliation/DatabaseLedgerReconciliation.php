<?php

declare(strict_types=1);

namespace Ledger\Infrastructure\Reconciliation;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Ledger\Application\Reconciliation\LedgerReconciliation;
use Ledger\Application\Reconciliation\LedgerReconciliationReport;
use LogicException;
use UnexpectedValueException;

/** Compares the journal, projection contributions, and current balances. */
final readonly class DatabaseLedgerReconciliation implements LedgerReconciliation
{
    public function __construct(private ConnectionInterface $connection) {}

    public function inspect(): LedgerReconciliationReport
    {
        return $this->connection->transaction(function (): LedgerReconciliationReport {
            // A repeatable snapshot prevents concurrent postings from creating false alarms.
            if ($this->driverName() === 'pgsql') {
                $this->connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            return new LedgerReconciliationReport(
                $this->unbalancedPostedEntryCount(),
                $this->contributionMismatchCount(),
                $this->balanceMismatchCount(),
            );
        });
    }

    private function unbalancedPostedEntryCount(): int
    {
        $totals = $this->connection->table('ledger_entries')
            ->leftJoin('ledger_postings', 'ledger_postings.entry_id', '=', 'ledger_entries.id')
            ->where('ledger_entries.status', 'posted')
            ->groupBy('ledger_entries.id')
            ->select('ledger_entries.id')
            ->selectRaw('COUNT(ledger_postings.id) AS posting_count')
            ->selectRaw("SUM(CASE WHEN ledger_postings.side IN ('debit', 'credit') THEN 1 ELSE 0 END) AS valid_posting_count")
            ->selectRaw('COUNT(DISTINCT ledger_postings.currency) AS currency_count')
            ->selectRaw("SUM(CASE WHEN ledger_postings.side = 'debit' THEN ledger_postings.minor_units ELSE 0 END) AS debits")
            ->selectRaw("SUM(CASE WHEN ledger_postings.side = 'credit' THEN ledger_postings.minor_units ELSE 0 END) AS credits");

        return $this->query()
            ->fromSub($totals, 'entry_totals')
            ->where(function (Builder $query): void {
                $query->where('posting_count', '<', 2)
                    ->orWhereColumn('valid_posting_count', '<>', 'posting_count')
                    ->orWhere('currency_count', '<>', 1)
                    ->orWhereColumn('debits', '<>', 'credits');
            })
            ->count();
    }

    private function contributionMismatchCount(): int
    {
        $missingOrChanged = $this->connection->table('ledger_postings')
            ->join('ledger_entries', 'ledger_entries.id', '=', 'ledger_postings.entry_id')
            ->leftJoin('ledger_balance_contributions', function ($join): void {
                $join->on('ledger_balance_contributions.entry_id', '=', 'ledger_postings.entry_id')
                    ->on('ledger_balance_contributions.ledger_id', '=', 'ledger_postings.ledger_id');
            })
            ->where('ledger_entries.status', 'posted')
            ->where(function (Builder $query): void {
                $query->whereNull('ledger_balance_contributions.entry_id')
                    ->orWhereColumn('ledger_balance_contributions.side', '<>', 'ledger_postings.side')
                    ->orWhereColumn('ledger_balance_contributions.minor_units', '<>', 'ledger_postings.minor_units');
            })
            ->count();

        $unexpected = $this->connection->table('ledger_balance_contributions')
            ->leftJoin('ledger_postings', function ($join): void {
                $join->on('ledger_postings.entry_id', '=', 'ledger_balance_contributions.entry_id')
                    ->on('ledger_postings.ledger_id', '=', 'ledger_balance_contributions.ledger_id');
            })
            ->leftJoin('ledger_entries', 'ledger_entries.id', '=', 'ledger_balance_contributions.entry_id')
            ->where(function (Builder $query): void {
                $query->whereNull('ledger_postings.id')
                    ->orWhere('ledger_entries.status', '<>', 'posted');
            })
            ->count();

        return $missingOrChanged + $unexpected;
    }

    private function balanceMismatchCount(): int
    {
        $expected = $this->expectedBalances();

        $missingOrChanged = $this->query()
            ->fromSub(clone $expected, 'expected_balances')
            ->leftJoin('ledger_balances', 'ledger_balances.ledger_id', '=', 'expected_balances.ledger_id')
            ->where(function (Builder $query): void {
                $query->whereNull('ledger_balances.ledger_id')
                    ->orWhereColumn('ledger_balances.currency', '<>', 'expected_balances.currency')
                    ->orWhereColumn('ledger_balances.debit_minor_units', '<>', 'expected_balances.debits')
                    ->orWhereColumn('ledger_balances.credit_minor_units', '<>', 'expected_balances.credits')
                    ->orWhereColumn('ledger_balances.balance_minor_units', '<>', 'expected_balances.balance');
            })
            ->count();

        $unexpected = $this->connection->table('ledger_balances')
            ->leftJoinSub($expected, 'expected_balances', function ($join): void {
                $join->on('expected_balances.ledger_id', '=', 'ledger_balances.ledger_id');
            })
            ->whereNull('expected_balances.ledger_id')
            ->count();

        return $missingOrChanged + $unexpected;
    }

    private function expectedBalances(): Builder
    {
        return $this->connection->table('ledger_postings')
            ->join('ledger_entries', 'ledger_entries.id', '=', 'ledger_postings.entry_id')
            ->where('ledger_entries.status', 'posted')
            ->groupBy('ledger_postings.ledger_id', 'ledger_postings.currency')
            ->select('ledger_postings.ledger_id', 'ledger_postings.currency')
            ->selectRaw("SUM(CASE WHEN ledger_postings.side = 'debit' THEN ledger_postings.minor_units ELSE 0 END) AS debits")
            ->selectRaw("SUM(CASE WHEN ledger_postings.side = 'credit' THEN ledger_postings.minor_units ELSE 0 END) AS credits")
            ->selectRaw("SUM(CASE WHEN ledger_postings.side = 'credit' THEN ledger_postings.minor_units ELSE -ledger_postings.minor_units END) AS balance");
    }

    private function query(): Builder
    {
        $makeQuery = [$this->connection, 'query'];

        if (! is_callable($makeQuery)) {
            throw new LogicException('The database connection must create query builders.');
        }

        $query = $makeQuery();

        if (! $query instanceof Builder) {
            throw new UnexpectedValueException('The database connection returned an invalid query builder.');
        }

        return $query;
    }

    private function driverName(): string
    {
        $readDriverName = [$this->connection, 'getDriverName'];

        if (! is_callable($readDriverName)) {
            throw new LogicException('The database connection must expose its driver name.');
        }

        $driverName = $readDriverName();

        if (! is_string($driverName)) {
            throw new UnexpectedValueException('The database driver name must be text.');
        }

        return $driverName;
    }
}
