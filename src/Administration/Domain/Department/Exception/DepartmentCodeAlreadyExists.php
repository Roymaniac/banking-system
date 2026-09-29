<?php

declare(strict_types=1);

namespace Administration\Domain\Department\Exception;

use Administration\Domain\Department\ValueObject\DepartmentCode;
use Shared\Domain\Exception\DomainException;

final class DepartmentCodeAlreadyExists extends DomainException
{
    public static function forCode(DepartmentCode $code): self
    {
        return new self(sprintf('A department with code %s already exists.', $code->value));
    }
}
