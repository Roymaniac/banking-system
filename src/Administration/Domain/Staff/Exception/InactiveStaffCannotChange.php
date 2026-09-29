<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\Exception;

use Shared\Domain\Exception\DomainException;

final class InactiveStaffCannotChange extends DomainException
{
    public static function create(): self
    {
        return new self('An inactive staff member cannot be changed.');
    }
}
