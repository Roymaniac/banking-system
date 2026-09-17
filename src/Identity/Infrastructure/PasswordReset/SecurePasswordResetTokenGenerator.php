<?php

declare(strict_types=1);

namespace Identity\Infrastructure\PasswordReset;

use Identity\Application\PasswordReset\PasswordResetTokenGenerator;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;

/**
 * Uses PHP's cryptographically secure random generator to create 256-bit
 * password-reset tokens.
 */
final class SecurePasswordResetTokenGenerator implements PasswordResetTokenGenerator
{
    public function generate(): PasswordResetToken
    {
        return new PasswordResetToken(bin2hex(random_bytes(32)));
    }
}
