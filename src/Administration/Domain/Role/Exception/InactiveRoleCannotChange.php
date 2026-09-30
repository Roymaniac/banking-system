<?php

declare(strict_types=1);

namespace Administration\Domain\Role\Exception;

use Shared\Domain\Exception\DomainException;

final class InactiveRoleCannotChange extends DomainException
{
    public static function create(): self
    {
        return new self('An inactive role cannot be changed or assigned.');
    }
}
