<?php

declare(strict_types=1);

namespace Identity\Domain\Authentication\Exception;

use Shared\Domain\Exception\DomainException;

final class MultiFactorAlreadyEnabled extends DomainException
{
    public static function create(): self
    {
        return new self('Multi-factor authentication is already enabled.');
    }
}
