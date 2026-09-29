<?php

declare(strict_types=1);

namespace Administration\Domain\Department\Exception;

use Shared\Domain\Exception\DomainException;

final class DepartmentNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('The requested department does not exist.');
    }
}
