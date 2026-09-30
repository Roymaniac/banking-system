<?php

declare(strict_types=1);

namespace Administration\Domain\Role\ValueObject;

enum RoleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
