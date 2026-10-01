<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Transaction\Domain\DailyLimit\DailyTransactionLimit;

/** Shows the bank ceiling, customer preference, and today's remaining allowance. */
final class DailyTransactionLimitResource extends JsonResource
{
    /** @return array<string, int|string|null> */
    public function toArray(Request $request): array
    {
        /** @var array{limit: DailyTransactionLimit, used: int} $data */
        $data = $this->resource;
        $limit = $data['limit'];
        $used = $data['used'];
        $effective = $limit->effectiveMaximumMinorUnits();

        return [
            'currency' => $limit->currency()->value(),
            'bank_maximum_minor_units' => $limit->maximumMinorUnits(),
            'customer_maximum_minor_units' => $limit->customerMaximumMinorUnits(),
            'effective_maximum_minor_units' => $effective,
            'used_minor_units' => $used,
            'remaining_minor_units' => max(0, $effective - $used),
            'configured_at' => $limit->configuredAt()->format(DATE_ATOM),
        ];
    }
}
