<?php

declare(strict_types=1);

namespace Identity\Application\PasswordReset;

use DateTimeImmutable;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;
use Identity\Domain\User\ValueObject\EmailAddress;

/**
 * Delivers the secret reset token without coupling Identity to email or SMS.
 */
interface PasswordResetNotifier
{
    public function send(
        EmailAddress $email,
        PasswordResetToken $token,
        DateTimeImmutable $expiresAt,
    ): void;
}
