<?php

declare(strict_types=1);

namespace Transaction\Domain\Deposit\Exception;

use Shared\Domain\Exception\DomainException;

final class AccountNotEligibleForDeposit extends DomainException
{
    public static function create(): self
    {
        return new self('An active account is required to complete a deposit.');
    }
}
