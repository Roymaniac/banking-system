<?php

declare(strict_types=1);

namespace Transaction\Domain\DailyLimit\Exception;

use Shared\Domain\Exception\DomainException;

final class AccountNotEligibleForDailyLimit extends DomainException
{
    public static function create(): self
    {
        return new self('An existing account is required to configure a daily transaction limit.');
    }
}
