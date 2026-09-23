<?php

declare(strict_types=1);

namespace Transaction\Application\DailyLimit;

use Account\Domain\Account\ValueObject\AccountId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries the account and its new maximum outgoing amount per day. */
final readonly class ConfigureDailyTransactionLimitCommand
{
    public function __construct(
        public AccountId $accountId,
        public int $maximumMinorUnits,
        public ?CorrelationId $correlationId = null,
    ) {}
}
