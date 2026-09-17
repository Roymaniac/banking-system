<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Exception;

use Shared\Domain\Exception\DomainException;

final class DuplicateCustomerAddress extends DomainException
{
    public static function create(): self
    {
        return new self('This address is already registered for the customer.');
    }
}
