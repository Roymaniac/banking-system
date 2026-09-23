<?php

declare(strict_types=1);

namespace Transaction\Domain\DailyLimit\Exception;

use Shared\Domain\Exception\DomainException;

final class DailyLimitCurrencyMismatch extends DomainException
{
    public static function create(): self
    {
        return new self('The daily limit currency does not match the transaction currency.');
    }
}
