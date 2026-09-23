<?php

declare(strict_types=1);

namespace Transaction\Domain\DailyLimit\Repository;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Transaction\Domain\DailyLimit\DailyTransactionLimit;

/** Stores account limits and safely reserves part of each day's allowance. */
interface DailyTransactionLimitRepository
{
    public function save(DailyTransactionLimit $limit): void;

    public function find(AccountId $accountId): ?DailyTransactionLimit;

    /** Adds outgoing spending while locking the account limit against concurrent requests. */
    public function consume(
        AccountId $accountId,
        LedgerCurrency $currency,
        int $minorUnits,
        DateTimeImmutable $bankingTime
    ): void;
}
