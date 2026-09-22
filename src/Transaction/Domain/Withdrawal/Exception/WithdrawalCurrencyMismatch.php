<?php

declare(strict_types=1);

namespace Transaction\Domain\Withdrawal\Exception;

use Shared\Domain\Exception\DomainException;

final class WithdrawalCurrencyMismatch extends DomainException
{
    public static function create(): self
    {
        return new self('The account, customer ledger, and disbursement ledger must use the same currency.');
    }
}
