<?php

declare(strict_types=1);

namespace Account\Application\Freeze;

use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\FreezeReason;
use Shared\Domain\Identifier\CorrelationId;

/** Identifies the account to restrict and the controlled business reason. */
final readonly class FreezeAccountCommand
{
    public function __construct(
        public AccountId $accountId,
        public FreezeReason $reason,
        public ?CorrelationId $correlationId = null,
    ) {}
}
