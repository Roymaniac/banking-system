<?php

declare(strict_types=1);

namespace Transaction\Domain\Deposit\Exception;

use Shared\Domain\Exception\DomainException;

final class DepositLedgerUnavailable extends DomainException
{
    public static function create(): self
    {
        return new self('The required customer or funding ledger is unavailable.');
    }
}
