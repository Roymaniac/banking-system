<?php

declare(strict_types=1);

namespace Administration\Domain\Permission\Exception;

use Shared\Domain\Exception\DomainException;

final class PermissionNameAlreadyExists extends DomainException
{
    public static function create(): self
    {
        return new self('A permission with this name already exists.');
    }
}
