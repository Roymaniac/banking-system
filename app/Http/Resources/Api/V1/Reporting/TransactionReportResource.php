<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reporting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Reporting\Application\Transaction\View\TransactionLineView;
use Reporting\Application\Transaction\View\TransactionReportView;

/** Presents an account statement using integer minor units for exact money values. */
final class TransactionReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TransactionReportView $report */
        $report = $this->resource;

        return [
            'account_id' => $report->accountId,
            'account_number' => $report->accountNumber,
            'currency' => $report->currency,
            'period' => [
                'from' => $report->period->from->format(DATE_ATOM),
                'to' => $report->period->to->format(DATE_ATOM),
            ],
            'opening_balance_minor_units' => $report->openingBalanceMinorUnits,
            'total_debit_minor_units' => $report->totalDebitMinorUnits,
            'total_credit_minor_units' => $report->totalCreditMinorUnits,
            'closing_balance_minor_units' => $report->closingBalanceMinorUnits,
            'transactions' => array_map(
                fn (TransactionLineView $transaction): array => [
                    'ledger_entry_id' => $transaction->ledgerEntryId,
                    'reference' => $transaction->reference,
                    'description' => $transaction->description,
                    'transaction_type' => $transaction->transactionType,
                    'side' => $transaction->side,
                    'minor_units' => $transaction->minorUnits,
                    'balance_after_minor_units' => $transaction->balanceAfterMinorUnits,
                    'occurred_at' => $transaction->occurredAt->format(DATE_ATOM),
                ],
                $report->transactions
            ),
            'pagination' => [
                'page' => $report->period->page,
                'per_page' => $report->period->perPage,
                'total' => $report->totalTransactions,
                'last_page' => max(1, (int) ceil($report->totalTransactions / $report->period->perPage)),
                'has_next_page' => $report->hasNextPage(),
            ],
        ];
    }
}
