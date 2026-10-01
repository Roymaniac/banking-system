<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Ledger;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ledger\Domain\Balance\LedgerBalance;

/** Exposes the customer-facing balance without internal accounting totals. */
final class BalanceResource extends JsonResource
{
    /** @return array{currency: string, balance_minor_units: int} */
    public function toArray(Request $request): array
    {
        /** @var LedgerBalance $balance */
        $balance = $this->resource;

        return [
            'currency' => $balance->currency()->value(),
            'balance_minor_units' => $balance->balanceMinorUnits(),
        ];
    }
}
