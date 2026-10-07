<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Transaction\Domain\MultipleTransfer\MultipleTransfer;

/**
 * Returns a safe receipt for a completed atomic batch transfer.
 *
 * @mixin MultipleTransfer
 */
final class MultipleTransferResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        /** @var MultipleTransfer $transfer */
        $transfer = $this->resource;

        return [
            'id' => $transfer->id()->value(),
            'reference' => $transfer->reference()->value(),
            'total_minor_units' => $transfer->totalAmount()->minorUnits(),
            'currency' => $transfer->totalAmount()->currency()->value(),
            'recipient_count' => count($transfer->items()),
            'status' => 'completed',
            'completed_at' => $transfer->completedAt()->format(DATE_ATOM),
        ];
    }
}
