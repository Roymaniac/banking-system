<?php

declare(strict_types=1);

namespace Compliance\Infrastructure\Persistence;

use Compliance\Application\DueDiligence\CustomerDueDiligenceChecker;
use Compliance\Application\DueDiligence\DueDiligenceGateway;
use Compliance\Domain\DueDiligence\Exception\InvalidDueDiligenceAction;
use Customer\Domain\Customer\ValueObject\CustomerId;

final readonly class DatabaseCustomerDueDiligenceChecker implements CustomerDueDiligenceChecker
{
    public function __construct(private DueDiligenceGateway $dueDiligence) {}

    public function assertApproved(CustomerId $customerId): void
    {
        if (! $this->dueDiligence->isApproved($customerId)) {
            throw InvalidDueDiligenceAction::because(
                'Customer identity verification must be approved before the account can be activated.',
            );
        }
    }
}
