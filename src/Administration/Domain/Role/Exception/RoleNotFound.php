<?php

declare(strict_types=1);

namespace Administration\Domain\Role\Exception;

use Shared\Domain\Exception\DomainException;

final class RoleNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('The requested role does not exist.');
    }
}
