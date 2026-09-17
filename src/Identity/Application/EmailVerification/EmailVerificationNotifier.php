<?php

declare(strict_types=1);

namespace Identity\Application\EmailVerification;

use DateTimeImmutable;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationToken;
use Identity\Domain\User\ValueObject\EmailAddress;

/**
 * Delivers verification links without coupling Identity to email technology.
 */
interface EmailVerificationNotifier
{
    public function send(
        EmailAddress $email,
        EmailVerificationToken $token,
        DateTimeImmutable $expiresAt,
    ): void;
}
