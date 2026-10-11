<?php

declare(strict_types=1);

namespace Compliance\Application\DueDiligence;

/** A safe read model that deliberately excludes encrypted identity evidence. */
final readonly class DueDiligenceView
{
    public function __construct(
        public string $id,
        public string $customerId,
        public string $customerName,
        public string $dateOfBirth,
        public string $status,
        public string $documentType,
        public string $issuingCountry,
        public string $documentLastFour,
        public ?string $riskLevel,
        public string $submittedAt,
        public ?string $reviewedAt,
        public ?string $rejectionReason,
    ) {}
}
