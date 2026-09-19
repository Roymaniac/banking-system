<?php

declare(strict_types=1);

namespace Account\Domain\Account\Exception;

use Shared\Domain\Exception\DomainException;

final class AccountNumberAlreadyAssigned extends DomainException
{
    public static function create(): self
    {
        return new self('This account already has an account number.');
    }
}
