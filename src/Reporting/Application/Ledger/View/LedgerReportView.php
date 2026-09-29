<?php

declare(strict_types=1);

namespace Reporting\Application\Ledger\View;

use Reporting\Application\Ledger\LedgerReportRequest;

/** Paginated general-ledger entries plus whole-period control totals. */
final readonly class LedgerReportView
{
    /**
     * @param  list<LedgerCurrencySummaryView>  $currencySummaries
     * @param  list<LedgerEntryView>  $entries
     */
    public function __construct(
        public LedgerReportRequest $request,
        public int $totalPostedEntries,
        public int $draftEntryCount,
        public int $unbalancedPostedEntryCount,
        public array $currencySummaries,
        public array $entries,
    ) {}

    public function hasNextPage(): bool
    {
        return $this->request->page * $this->request->perPage < $this->totalPostedEntries;
    }
}
