<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\Exception;

use Shared\Domain\Exception\DomainException;

final class StaffAlreadyExists extends DomainException
{
    public static function create(): self
    {
        return new self('A staff record already exists for this user or employee number.');
    }
}
