<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Exception;

use Shared\Domain\Exception\DomainException;

final class UserNotEligibleForCustomerProfile extends DomainException
{
    public static function create(): self
    {
        // One public message avoids revealing whether a user ID exists.
        return new self('A verified user is required to create a customer profile.');
    }
}
