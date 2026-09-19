<?php

declare(strict_types=1);

namespace Account\Application\Closure;

use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\ClosureReason;
use Shared\Domain\Identifier\CorrelationId;

/** Identifies the account to close and the controlled business reason. */
final readonly class CloseAccountCommand
{
    public function __construct(
        public AccountId $accountId,
        public ClosureReason $reason,
        public ?CorrelationId $correlationId = null,
    ) {}
}
