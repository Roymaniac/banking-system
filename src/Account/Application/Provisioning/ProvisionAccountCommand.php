<?php

declare(strict_types=1);

namespace Account\Application\Provisioning;

use Account\Domain\Account\ValueObject\AccountId;
use Shared\Domain\Identifier\CorrelationId;

/** Identifies the pending account that staff want to make ready for use. */
final readonly class ProvisionAccountCommand
{
    public function __construct(
        public AccountId $accountId,
        public ?CorrelationId $correlationId = null,
    ) {}
}
