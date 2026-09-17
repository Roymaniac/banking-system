<?php

declare(strict_types=1);

namespace Identity\Domain\EmailVerification\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Uses one safe message for malformed, missing, expired, and used tokens.
 */
final class InvalidEmailVerificationToken extends DomainException
{
    public static function create(): self
    {
        return new self('The email-verification token is invalid or has expired.');
    }
}
