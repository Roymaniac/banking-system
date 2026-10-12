<?php

declare(strict_types=1);

namespace Identity\Domain\Authentication\Exception;

use Shared\Domain\Exception\DomainException;

/** Uses one response for incorrect, expired, and exhausted MFA challenges. */
final class InvalidMultiFactorCode extends DomainException
{
    public static function create(): self
    {
        return new self('The multi-factor authentication code is invalid or has expired.');
    }
}
