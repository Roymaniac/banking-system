<?php

declare(strict_types=1);

namespace Identity\Infrastructure\EmailVerification;

use Identity\Application\EmailVerification\EmailVerificationTokenGenerator;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationToken;

/**
 * Uses PHP's secure random generator to create 256-bit verification tokens.
 */
final class SecureEmailVerificationTokenGenerator implements EmailVerificationTokenGenerator
{
    public function generate(): EmailVerificationToken
    {
        return new EmailVerificationToken(bin2hex(random_bytes(32)));
    }
}
