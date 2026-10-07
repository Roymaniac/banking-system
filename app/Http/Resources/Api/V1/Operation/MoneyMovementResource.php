<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Transaction\Domain\Deposit\Deposit;
use Transaction\Domain\Withdrawal\Withdrawal;

/**
 * Returns a receipt without exposing internal ledger identifiers.
 *
 * Deposit and withdrawal aggregates expose the same receipt fields. Deposit
 * is named here so documentation tools can understand that shared shape.
 *
 * @mixin Deposit
 */
final class MoneyMovementResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        /** @var Deposit|Withdrawal $transaction */
        $transaction = $this->resource;

        return [
            'id' => $transaction->id()->value(),
            'reference' => $transaction->reference()->value(),
            'minor_units' => $transaction->amount()->minorUnits(),
            'currency' => $transaction->amount()->currency()->value(),
            'status' => 'completed',
            'completed_at' => $transaction->completedAt()->format(DATE_ATOM),
        ];
    }
}
