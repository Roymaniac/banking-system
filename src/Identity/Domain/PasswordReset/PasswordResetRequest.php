<?php

declare(strict_types=1);

namespace Identity\Domain\PasswordReset;

use DateTimeImmutable;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetTokenHash;
use Identity\Domain\User\ValueObject\EmailAddress;
use InvalidArgumentException;

/**
 * Represents one temporary, single-use request to choose a new password.
 */
final readonly class PasswordResetRequest
{
    public function __construct(
        private EmailAddress $email,
        private PasswordResetTokenHash $tokenHash,
        private DateTimeImmutable $requestedAt,
        private DateTimeImmutable $expiresAt,
    ) {
        if ($expiresAt <= $requestedAt) {
            throw new InvalidArgumentException('A password-reset request must expire after it is created.');
        }
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function tokenHash(): PasswordResetTokenHash
    {
        return $this->tokenHash;
    }

    public function requestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpiredAt(DateTimeImmutable $time): bool
    {
        return $time >= $this->expiresAt;
    }
}
