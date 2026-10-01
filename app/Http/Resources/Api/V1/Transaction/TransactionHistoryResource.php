<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Reporting\Application\Transaction\View\TransactionReportView;

/** Shapes statement totals, pagination, and posted transaction lines. */
final class TransactionHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TransactionReportView $report */
        $report = $this->resource;

        return [
            'account_number' => $report->accountNumber,
            'currency' => $report->currency,
            'period' => [
                'from' => $report->period->from->format(DATE_ATOM),
                'to' => $report->period->to->format(DATE_ATOM),
            ],
            'summary' => [
                'opening_balance_minor_units' => $report->openingBalanceMinorUnits,
                'total_debit_minor_units' => $report->totalDebitMinorUnits,
                'total_credit_minor_units' => $report->totalCreditMinorUnits,
                'closing_balance_minor_units' => $report->closingBalanceMinorUnits,
            ],
            'transactions' => TransactionLineResource::collection($report->transactions),
            'pagination' => [
                'page' => $report->period->page,
                'per_page' => $report->period->perPage,
                'total' => $report->totalTransactions,
                'has_next_page' => $report->hasNextPage(),
            ],
        ];
    }
}
