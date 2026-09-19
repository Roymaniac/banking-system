<?php

declare(strict_types=1);

namespace Account\Domain\Account\ValueObject;

/** Records the controlled business reason for permanently closing an account. */
enum ClosureReason: string
{
    case CustomerRequest = 'customer_request';
    case BankDecision = 'bank_decision';
    case Inactivity = 'inactivity';
    case ComplianceDecision = 'compliance_decision';
}
