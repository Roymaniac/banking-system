<?php

declare(strict_types=1);

namespace Account\Domain\Account\ValueObject;

/** Records the controlled business reason for restricting an account. */
enum FreezeReason: string
{
    case SuspectedFraud = 'suspected_fraud';
    case ComplianceReview = 'compliance_review';
    case CustomerRequest = 'customer_request';
    case CourtOrder = 'court_order';
}
