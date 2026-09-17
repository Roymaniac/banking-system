<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Exception;

use Shared\Domain\Exception\DomainException;

final class CustomerProfileAlreadyExists extends DomainException
{
    public static function create(): self
    {
        return new self('A customer profile already exists for this user.');
    }
}
