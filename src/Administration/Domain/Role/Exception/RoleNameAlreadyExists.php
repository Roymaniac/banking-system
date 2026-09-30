<?php

declare(strict_types=1);

namespace Administration\Domain\Role\Exception;

use Shared\Domain\Exception\DomainException;

final class RoleNameAlreadyExists extends DomainException
{
    public static function create(): self
    {
        return new self('A role with this name already exists.');
    }
}
