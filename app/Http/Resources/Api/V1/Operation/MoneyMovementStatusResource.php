<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Transaction\Application\Control\MoneyMovementStatus;

/**
 * Returns the protected operational state used during incident response.
 *
 * @mixin MoneyMovementStatus
 */
final class MoneyMovementStatusResource extends JsonResource
{
    /** @return array{enabled: bool, reason: ?string, source: string, changed_at: string, revision: int} */
    public function toArray(Request $request): array
    {
        /** @var MoneyMovementStatus $status */
        $status = $this->resource;

        return [
            'enabled' => $status->enabled,
            'reason' => $status->reason,
            'source' => $status->source,
            'changed_at' => $status->changedAt->format(DATE_ATOM),
            'revision' => $status->revision,
        ];
    }
}
