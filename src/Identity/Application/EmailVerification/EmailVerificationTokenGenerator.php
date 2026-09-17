<?php

declare(strict_types=1);

namespace Identity\Application\EmailVerification;

use Identity\Domain\EmailVerification\ValueObject\EmailVerificationToken;

/**
 * Generates unpredictable secrets for email-verification links.
 */
interface EmailVerificationTokenGenerator
{
    public function generate(): EmailVerificationToken;
}
