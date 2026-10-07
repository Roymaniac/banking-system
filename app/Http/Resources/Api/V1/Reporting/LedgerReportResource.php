<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reporting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Reporting\Application\Ledger\View\LedgerCurrencySummaryView;
use Reporting\Application\Ledger\View\LedgerEntryView;
use Reporting\Application\Ledger\View\LedgerPostingView;
use Reporting\Application\Ledger\View\LedgerReportView;

/**
 * Presents general-ledger entries together with financial control totals.
 *
 * @mixin LedgerReportView
 */
final class LedgerReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var LedgerReportView $report */
        $report = $this->resource;

        return [
            'period' => [
                'from' => $report->request->from->format(DATE_ATOM),
                'to' => $report->request->to->format(DATE_ATOM),
            ],
            'control_totals' => [
                'posted_entries' => $report->totalPostedEntries,
                'draft_entries' => $report->draftEntryCount,
                'unbalanced_posted_entries' => $report->unbalancedPostedEntryCount,
            ],
            'currency_summaries' => array_map(
                fn (LedgerCurrencySummaryView $summary): array => [
                    'currency' => $summary->currency,
                    'total_debit_minor_units' => $summary->totalDebitMinorUnits,
                    'total_credit_minor_units' => $summary->totalCreditMinorUnits,
                    'difference_minor_units' => $summary->differenceMinorUnits(),
                    'balanced' => $summary->isBalanced(),
                ],
                $report->currencySummaries
            ),
            'entries' => array_map(
                fn (LedgerEntryView $entry): array => [
                    'id' => $entry->id,
                    'reference' => $entry->reference,
                    'description' => $entry->description,
                    'occurred_at' => $entry->occurredAt->format(DATE_ATOM),
                    'recorded_at' => $entry->recordedAt->format(DATE_ATOM),
                    'total_debit_minor_units' => $entry->totalDebitMinorUnits,
                    'total_credit_minor_units' => $entry->totalCreditMinorUnits,
                    'balanced' => $entry->isBalanced(),
                    'postings' => array_map(
                        fn (LedgerPostingView $posting): array => [
                            'id' => $posting->postingId,
                            'ledger_id' => $posting->ledgerId,
                            'account_id' => $posting->accountId,
                            'account_number' => $posting->accountNumber,
                            'side' => $posting->side,
                            'minor_units' => $posting->minorUnits,
                            'currency' => $posting->currency,
                        ],
                        $entry->postings
                    ),
                ],
                $report->entries
            ),
            'pagination' => [
                'page' => $report->request->page,
                'per_page' => $report->request->perPage,
                'total' => $report->totalPostedEntries,
                'last_page' => max(1, (int) ceil($report->totalPostedEntries / $report->request->perPage)),
                'has_next_page' => $report->hasNextPage(),
            ],
        ];
    }
}
