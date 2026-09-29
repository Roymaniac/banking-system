<?php

declare(strict_types=1);

namespace Administration\Domain\Department\ValueObject;

enum DepartmentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
