<?php

declare(strict_types=1);

namespace Transaction\Domain\DailyLimit\Exception;

use Shared\Domain\Exception\DomainException;

final class DailyTransactionLimitNotConfigured extends DomainException
{
    public static function create(): self
    {
        return new self('The bank has not configured a daily transaction limit for this account.');
    }
}
