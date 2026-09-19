<?php

declare(strict_types=1);

namespace Account\Application\Number;

use Account\Domain\Account\ValueObject\AccountId;
use Shared\Domain\Identifier\CorrelationId;

/** Identifies the account that needs its customer-facing number. */
final readonly class AssignAccountNumberCommand
{
    public function __construct(
        public AccountId $accountId,
        public ?CorrelationId $correlationId = null,
    ) {}
}
