<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Transaction\Application\Risk\TransferRiskCase;

/**
 * Returns investigation state without exposing account or transfer details.
 *
 * @mixin TransferRiskCase
 */
final class TransferRiskCaseResource extends JsonResource
{
    /** @return array<string, int|null|string> */
    public function toArray(Request $request): array
    {
        /** @var TransferRiskCase $riskCase */
        $riskCase = $this->resource;

        return [
            'id' => $riskCase->id->value(),
            'account_last_four' => $riskCase->accountLastFour,
            'rule' => $riskCase->rule,
            'status' => $riskCase->status,
            'window_minutes' => $riskCase->windowMinutes,
            'maximum_transfers' => $riskCase->maximumTransfers,
            'detected_at' => $riskCase->detectedAt->format(DATE_ATOM),
            'claimed_by' => $riskCase->claimedBy?->value(),
            'claimed_at' => $riskCase->claimedAt?->format(DATE_ATOM),
            'resolved_by' => $riskCase->resolvedBy?->value(),
            'resolution' => $riskCase->resolution,
            'resolution_note' => $riskCase->resolutionNote,
            'resolved_at' => $riskCase->resolvedAt?->format(DATE_ATOM),
        ];
    }
}
