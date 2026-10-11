<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Compliance;

use Compliance\Application\DueDiligence\DueDiligenceView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shows review status while never returning the complete identity document number.
 *
 * @mixin DueDiligenceView
 */
final class DueDiligenceResource extends JsonResource
{
    public function __construct(DueDiligenceView $view)
    {
        parent::__construct($view);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var DueDiligenceView $view */
        $view = $this->resource;

        return [
            'id' => $view->id,
            'customer_id' => $view->customerId,
            'customer_name' => $view->customerName,
            'date_of_birth' => $view->dateOfBirth,
            'status' => $view->status,
            'document_type' => $view->documentType,
            'issuing_country' => $view->issuingCountry,
            'document_last_four' => $view->documentLastFour,
            'risk_level' => $view->riskLevel,
            'submitted_at' => $view->submittedAt,
            'reviewed_at' => $view->reviewedAt,
            'rejection_reason' => $view->rejectionReason,
        ];
    }
}
