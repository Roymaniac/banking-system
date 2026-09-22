<?php

declare(strict_types=1);

namespace Transaction\Domain\Deposit\Exception;

use Shared\Domain\Exception\DomainException;

final class DepositCurrencyMismatch extends DomainException
{
    public static function create(): self
    {
        return new self('The account, customer ledger, and funding ledger must use the same currency.');
    }
}
