<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Transaction\Domain\Reversal\Reversal;

/**
 * Returns the public receipt for a completed reversal.
 *
 * @mixin Reversal
 */
final class ReversalResource extends JsonResource
{
    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        /** @var Reversal $reversal */
        $reversal = $this->resource;

        return [
            'id' => $reversal->id()->value(),
            'reference' => $reversal->reference()->value(),
            'reason' => $reversal->reason()->value(),
            'status' => 'completed',
            'completed_at' => $reversal->completedAt()->format(DATE_ATOM),
        ];
    }
}
