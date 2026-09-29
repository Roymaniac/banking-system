<?php

declare(strict_types=1);

namespace Administration\Domain\Department\Exception;

use Shared\Domain\Exception\DomainException;

final class InactiveDepartmentCannotChange extends DomainException
{
    public static function create(): self
    {
        return new self('An inactive department cannot be changed.');
    }
}
