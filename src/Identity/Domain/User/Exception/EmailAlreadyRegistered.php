<?php

declare(strict_types=1);

namespace Identity\Domain\User\Exception;

use Shared\Domain\Exception\DomainException;

final class EmailAlreadyRegistered extends DomainException
{
    public static function create(): self
    {
        return new self('An account already uses this email address.');
    }
}
