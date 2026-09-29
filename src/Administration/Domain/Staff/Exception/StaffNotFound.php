<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\Exception;

use Shared\Domain\Exception\DomainException;

final class StaffNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('The requested staff member does not exist.');
    }
}
