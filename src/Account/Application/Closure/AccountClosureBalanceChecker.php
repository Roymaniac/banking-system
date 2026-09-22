<?php

declare(strict_types=1);

namespace Account\Application\Closure;

use Account\Domain\Account\ValueObject\AccountId;

/** Allows the Ledger module to enforce financial eligibility for closure. */
interface AccountClosureBalanceChecker
{
    public function assertZeroBalance(AccountId $accountId): void;
}
