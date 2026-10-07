<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Transaction\Domain\Transfer\Transfer;

/**
 * Returns a safe receipt for a completed customer transfer.
 *
 * @mixin Transfer
 */
final class TransferResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        /** @var Transfer $transfer */
        $transfer = $this->resource;

        return [
            'id' => $transfer->id()->value(),
            'reference' => $transfer->reference()->value(),
            'minor_units' => $transfer->amount()->minorUnits(),
            'currency' => $transfer->amount()->currency()->value(),
            'status' => 'completed',
            'completed_at' => $transfer->completedAt()->format(DATE_ATOM),
        ];
    }
}
