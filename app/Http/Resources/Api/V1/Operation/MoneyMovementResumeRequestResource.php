<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Transaction\Application\Control\MoneyMovementResumeRequest;

/**
 * Returns the safe approval state for one resumption request.
 *
 * @mixin MoneyMovementResumeRequest
 */
final class MoneyMovementResumeRequestResource extends JsonResource
{
    /** @return array<string, null|string> */
    public function toArray(Request $request): array
    {
        /** @var MoneyMovementResumeRequest $resumeRequest */
        $resumeRequest = $this->resource;

        return [
            'id' => $resumeRequest->id->value(),
            'requested_by' => $resumeRequest->requestedBy->value(),
            'reason' => $resumeRequest->reason,
            'status' => $resumeRequest->status,
            'requested_at' => $resumeRequest->requestedAt->format(DATE_ATOM),
            'expires_at' => $resumeRequest->expiresAt->format(DATE_ATOM),
            'approved_by' => $resumeRequest->approvedBy?->value(),
            'approved_at' => $resumeRequest->approvedAt?->format(DATE_ATOM),
        ];
    }
}
