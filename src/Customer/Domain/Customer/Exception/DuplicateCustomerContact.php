<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Exception;

use Shared\Domain\Exception\DomainException;

final class DuplicateCustomerContact extends DomainException
{
    public static function create(): self
    {
        return new self('This contact is already registered for the customer.');
    }
}
