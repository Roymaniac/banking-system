<?php

declare(strict_types=1);

namespace Account\Application\Freeze;

use Account\Domain\Account\ValueObject\AccountId;
use Shared\Domain\Identifier\CorrelationId;

/** Identifies the frozen account that should be restored. */
final readonly class UnfreezeAccountCommand
{
    public function __construct(
        public AccountId $accountId,
        public ?CorrelationId $correlationId = null,
    ) {}
}
