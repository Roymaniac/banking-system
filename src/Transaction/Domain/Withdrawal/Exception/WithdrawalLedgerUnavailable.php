<?php

declare(strict_types=1);

namespace Transaction\Domain\Withdrawal\Exception;

use Shared\Domain\Exception\DomainException;

final class WithdrawalLedgerUnavailable extends DomainException
{
    public static function create(): self
    {
        return new self('The required customer or disbursement ledger is unavailable.');
    }
}
