<?php

declare(strict_types=1);

namespace Compliance\Application\DueDiligence;

use Customer\Domain\Customer\ValueObject\CustomerId;

interface CustomerDueDiligenceChecker
{
    public function assertApproved(CustomerId $customerId): void;
}
