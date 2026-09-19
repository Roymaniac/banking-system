<?php

declare(strict_types=1);

namespace Account\Domain\Account\Exception;

use Shared\Domain\Exception\DomainException;

final class AccountNumberRequired extends DomainException
{
    public static function create(): self
    {
        return new self('An account number must be assigned before the account can be activated.');
    }
}
