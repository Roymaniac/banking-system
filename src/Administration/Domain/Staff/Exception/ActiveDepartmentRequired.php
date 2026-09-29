<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\Exception;

use Shared\Domain\Exception\DomainException;

final class ActiveDepartmentRequired extends DomainException
{
    public static function create(): self
    {
        return new self('Staff can only be assigned to an active department.');
    }
}
