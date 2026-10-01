<?php

declare(strict_types=1);

namespace Transaction\Application\DailyLimit;

use Account\Domain\Account\ValueObject\AccountId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries a customer's requested lower daily limit. */
final readonly class ReduceDailyTransactionLimitCommand
{
    public function __construct(
        public AccountId $accountId,
        public int $maximumMinorUnits,
        public ?CorrelationId $correlationId = null,
    ) {}
}
