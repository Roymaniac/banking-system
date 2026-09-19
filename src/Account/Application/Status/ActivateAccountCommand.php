<?php

declare(strict_types=1);

namespace Account\Application\Status;

use Account\Domain\Account\ValueObject\AccountId;
use Shared\Domain\Identifier\CorrelationId;

/** Identifies the provisioned account that should become usable. */
final readonly class ActivateAccountCommand
{
    public function __construct(
        public AccountId $accountId,
        public ?CorrelationId $correlationId = null,
    ) {}
}
