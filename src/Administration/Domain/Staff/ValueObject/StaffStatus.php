<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\ValueObject;

enum StaffStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
