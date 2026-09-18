<?php

declare(strict_types=1);

namespace Account\Domain\Account\Exception;

use Shared\Domain\Exception\DomainException;

final class AccountNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('The requested account does not exist.');
    }
}
