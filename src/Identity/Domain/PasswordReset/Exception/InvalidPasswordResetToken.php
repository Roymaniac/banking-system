<?php

declare(strict_types=1);

namespace Identity\Domain\PasswordReset\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Uses the same message for missing, expired, and already-used tokens so the
 * failure does not expose internal account information.
 */
final class InvalidPasswordResetToken extends DomainException
{
    public static function create(): self
    {
        return new self('The password-reset token is invalid or has expired.');
    }
}
