<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Exception;

use Shared\Domain\Exception\DomainException;

final class CustomerAddressNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('The requested customer address does not exist.');
    }
}
