<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Transaction\Application\Control\MoneyMovementControlEventView;

/**
 * Returns one protected and immutable incident-control record.
 *
 * @mixin MoneyMovementControlEventView
 */
final class MoneyMovementControlEventResource extends JsonResource
{
    /** @return array{id: int, action: string, reason: string, source: string, actor_user_id: ?string, occurred_at: string} */
    public function toArray(Request $request): array
    {
        /** @var MoneyMovementControlEventView $event */
        $event = $this->resource;

        return [
            'id' => $event->id,
            'action' => $event->action,
            'reason' => $event->reason,
            'source' => $event->source,
            'actor_user_id' => $event->actorUserId,
            'occurred_at' => $event->occurredAt->format(DATE_ATOM),
        ];
    }
}
