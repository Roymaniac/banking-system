<?php

declare(strict_types=1);

namespace Reporting\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Reporting\Application\Ledger\LedgerReportQuery;
use Reporting\Application\Ledger\LedgerReportRequest;
use Reporting\Application\Ledger\View\LedgerCurrencySummaryView;
use Reporting\Application\Ledger\View\LedgerEntryView;
use Reporting\Application\Ledger\View\LedgerPostingView;
use Reporting\Application\Ledger\View\LedgerReportView;

/** Reads complete journal entries and accounting control totals from storage. */
final readonly class DatabaseLedgerReportQuery implements LedgerReportQuery
{
    public function __construct(
        private ConnectionInterface $connection,
        private DatabaseReportSnapshot $snapshot,
    ) {}

    public function generate(LedgerReportRequest $request): LedgerReportView
    {
        return $this->snapshot->run(
            fn(): LedgerReportView => $this->generateFromSnapshot($request)
        );
    }

    private function generateFromSnapshot(LedgerReportRequest $request): LedgerReportView
    {
        $from = $request->from->setTimezone(new DateTimeZone('UTC'));
        $to = $request->to->setTimezone(new DateTimeZone('UTC'));
        $periodEntries = fn(): Builder => $this->connection->table('ledger_entries')
            ->whereBetween('occurred_at', [$from, $to]);

        $totalPostedEntries = (clone $periodEntries())
            ->where('status', 'posted')
            ->count();

        $draftEntryCount = (clone $periodEntries())
            ->where('status', 'draft')
            ->count();

        $entryBalances = $this->connection->table('ledger_postings')
            ->join('ledger_entries', 'ledger_entries.id', '=', 'ledger_postings.entry_id')
            ->where('ledger_entries.status', 'posted')
            ->whereBetween('ledger_entries.occurred_at', [$from, $to])
            ->groupBy('ledger_entries.id')
            ->select('ledger_entries.id')
            ->selectRaw("SUM(CASE WHEN ledger_postings.side = 'debit' THEN ledger_postings.minor_units ELSE 0 END) AS debits")
            ->selectRaw("SUM(CASE WHEN ledger_postings.side = 'credit' THEN ledger_postings.minor_units ELSE 0 END) AS credits");

        $unbalancedCount = $this->connection->query()
            ->fromSub($entryBalances, 'entry_balances')
            ->whereColumn('debits', '<>', 'credits')
            ->count();

        $currencySummaries = $this->connection->table('ledger_postings')
            ->join('ledger_entries', 'ledger_entries.id', '=', 'ledger_postings.entry_id')
            ->where('ledger_entries.status', 'posted')
            ->whereBetween('ledger_entries.occurred_at', [$from, $to])
            ->groupBy('ledger_postings.currency')
            ->orderBy('ledger_postings.currency')
            ->select('ledger_postings.currency')
            ->selectRaw("SUM(CASE WHEN ledger_postings.side = 'debit' THEN ledger_postings.minor_units ELSE 0 END) AS debits")
            ->selectRaw("SUM(CASE WHEN ledger_postings.side = 'credit' THEN ledger_postings.minor_units ELSE 0 END) AS credits")
            ->get()
            ->map(
                fn(object $summary): LedgerCurrencySummaryView => new LedgerCurrencySummaryView(
                    $summary->currency,
                    (int) $summary->debits,
                    (int) $summary->credits,
                )
            )
            ->all();

        $entryRecords = (clone $periodEntries())
            ->where('status', 'posted')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->offset(($request->page - 1) * $request->perPage)
            ->limit($request->perPage)
            ->get();

        $entryIds = $entryRecords->pluck('id')->all();
        $postingsByEntry = $this->postingsByEntry($entryIds);
        $entries = [];

        foreach ($entryRecords as $entry) {
            $postings = $postingsByEntry[$entry->id] ?? [];
            $debits = 0;
            $credits = 0;

            foreach ($postings as $posting) {
                if ($posting->side === 'debit') {
                    $debits += $posting->minorUnits;
                } else {
                    $credits += $posting->minorUnits;
                }
            }

            $entries[] = new LedgerEntryView(
                $entry->id,
                $entry->reference,
                $entry->description,
                new DateTimeImmutable($entry->occurred_at),
                new DateTimeImmutable($entry->recorded_at),
                $debits,
                $credits,
                $postings,
            );
        }

        return new LedgerReportView(
            $request,
            $totalPostedEntries,
            $draftEntryCount,
            $unbalancedCount,
            $currencySummaries,
            $entries,
        );
    }

    /**
     * @param  list<string>  $entryIds
     * @return array<string, list<LedgerPostingView>>
     */
    private function postingsByEntry(array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        $grouped = [];
        $records = $this->connection->table('ledger_postings')
            ->join('ledgers', 'ledgers.id', '=', 'ledger_postings.ledger_id')
            ->join('accounts', 'accounts.id', '=', 'ledgers.account_id')
            ->whereIn('ledger_postings.entry_id', $entryIds)
            ->orderBy('ledger_postings.entry_id')
            ->orderBy('ledger_postings.side')
            ->orderBy('ledger_postings.id')
            ->select(
                'ledger_postings.id',
                'ledger_postings.entry_id',
                'ledger_postings.ledger_id',
                'ledger_postings.side',
                'ledger_postings.minor_units',
                'ledger_postings.currency',
                'accounts.id as account_id',
                'accounts.number as account_number',
            )
            ->get();

        foreach ($records as $record) {
            $grouped[$record->entry_id][] = new LedgerPostingView(
                $record->id,
                $record->ledger_id,
                $record->account_id,
                $record->account_number,
                $record->side,
                (int) $record->minor_units,
                $record->currency,
            );
        }

        return $grouped;
    }
}
