<?php

declare(strict_types=1);

namespace Identity\Application\PasswordReset;

use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;

/**
 * Generates unpredictable secrets for password-reset links.
 */
interface PasswordResetTokenGenerator
{
    public function generate(): PasswordResetToken;
}
