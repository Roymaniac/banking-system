<?php

declare(strict_types=1);

namespace Reporting\Infrastructure\Persistence;

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Reporting\Application\Transaction\TransactionReportPeriod;
use Reporting\Application\Transaction\TransactionReportQuery;
use Reporting\Application\Transaction\View\TransactionLineView;
use Reporting\Application\Transaction\View\TransactionReportView;

/** Builds account statements from the authoritative posted ledger lines. */
final readonly class DatabaseTransactionReportQuery implements TransactionReportQuery
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function find(
        AccountId $accountId,
        TransactionReportPeriod $period
    ): ?TransactionReportView {

        $account = $this->connection->table('accounts')
            ->join('ledgers', 'ledgers.account_id', '=', 'accounts.id')
            ->where('accounts.id', $accountId->value())
            ->select('accounts.id', 'accounts.number', 'ledgers.id as ledger_id', 'ledgers.currency')
            ->first();

        if ($account === null) {
            return null;
        }

        $from = $period->from->setTimezone(new DateTimeZone('UTC'));
        $to = $period->to->setTimezone(new DateTimeZone('UTC'));
        $openingBalance = (int) $this->connection->table('ledger_postings')
            ->join('ledger_entries', 'ledger_entries.id', '=', 'ledger_postings.entry_id')
            ->where('ledger_postings.ledger_id', $account->ledger_id)
            ->where('ledger_entries.status', EntryStatus::Posted)
            ->where('ledger_entries.occurred_at', '<', $from)
            ->selectRaw("COALESCE(SUM(CASE WHEN ledger_postings.side = 'credit' THEN ledger_postings.minor_units ELSE -ledger_postings.minor_units END), 0) AS balance")
            ->value('balance');

        $records = $this->connection->table('ledger_postings')
            ->join('ledger_entries', 'ledger_entries.id', '=', 'ledger_postings.entry_id')
            ->where('ledger_postings.ledger_id', $account->ledger_id)
            ->where('ledger_entries.status', EntryStatus::Posted)
            ->whereBetween('ledger_entries.occurred_at', [$from, $to])
            ->orderBy('ledger_entries.occurred_at')
            ->orderBy('ledger_entries.id')
            ->select(
                'ledger_entries.id',
                'ledger_entries.reference',
                'ledger_entries.description',
                'ledger_entries.occurred_at',
                'ledger_postings.side',
                'ledger_postings.minor_units',
            )
            ->get();

        $entryIds = $records->pluck('id')->all();
        $types = $this->transactionTypes($entryIds);
        $runningBalance = $openingBalance;
        $totalDebits = 0;
        $totalCredits = 0;
        $transactions = [];

        foreach ($records as $record) {
            $amount = (int) $record->minor_units;

            if ($record->side === 'credit') {
                $totalCredits += $amount;
                $runningBalance += $amount;
            } else {
                $totalDebits += $amount;
                $runningBalance -= $amount;
            }

            $transactions[] = new TransactionLineView(
                $record->id,
                $record->reference,
                $record->description,
                $types[$record->id] ?? 'ledger_adjustment',
                $record->side,
                $amount,
                $runningBalance,
                new DateTimeImmutable($record->occurred_at),
            );
        }

        return new TransactionReportView(
            $account->id,
            $account->number,
            $account->currency,
            $period,
            $openingBalance,
            $totalDebits,
            $totalCredits,
            $runningBalance,
            $transactions,
        );
    }

    /**
     * Finds each entry's business transaction without changing the ledger source of truth.
     *
     * @param  list<string>  $entryIds
     * @return array<string, string>
     */
    private function transactionTypes(array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        $types = [];
        $sources = [
            ['deposits', 'ledger_entry_id', 'deposit'],
            ['withdrawals', 'ledger_entry_id', 'withdrawal'],
            ['transfers', 'ledger_entry_id', 'transfer'],
            ['multiple_transfers', 'ledger_entry_id', 'multiple_transfer'],
            ['transaction_reversals', 'reversal_ledger_entry_id', 'reversal'],
        ];

        foreach ($sources as [$table, $column, $type]) {
            foreach (
                $this->connection->table($table)
                    ->whereIn($column, $entryIds)
                    ->pluck($column) as $entryId
            ) {
                $types[(string) $entryId] = $type;
            }
        }

        return $types;
    }
}
