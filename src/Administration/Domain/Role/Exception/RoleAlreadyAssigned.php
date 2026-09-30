<?php

declare(strict_types=1);

namespace Administration\Domain\Role\Exception;

use Shared\Domain\Exception\DomainException;

final class RoleAlreadyAssigned extends DomainException
{
    public static function create(): self
    {
        return new self('This role is already assigned to the staff member.');
    }
}
