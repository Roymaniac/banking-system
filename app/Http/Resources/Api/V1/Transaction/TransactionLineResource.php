<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Reporting\Application\Transaction\View\TransactionLineView;

/** Converts one posted ledger line into a customer-safe transaction record. */
final class TransactionLineResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        /** @var TransactionLineView $transaction */
        $transaction = $this->resource;

        return [
            'reference' => $transaction->reference,
            'description' => $transaction->description,
            'type' => $transaction->transactionType,
            'direction' => $transaction->side,
            'minor_units' => $transaction->minorUnits,
            'balance_after_minor_units' => $transaction->balanceAfterMinorUnits,
            'occurred_at' => $transaction->occurredAt->format(DATE_ATOM),
        ];
    }
}
