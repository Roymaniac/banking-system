<?php

declare(strict_types=1);

namespace Identity\Domain\EmailVerification;

use DateTimeImmutable;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationTokenHash;
use Identity\Domain\User\ValueObject\UserId;
use InvalidArgumentException;

/**
 * Represents one temporary, single-use email-verification request.
 */
final readonly class EmailVerificationRequest
{
    public function __construct(
        private UserId $userId,
        private EmailVerificationTokenHash $tokenHash,
        private DateTimeImmutable $requestedAt,
        private DateTimeImmutable $expiresAt,
    ) {
        if ($expiresAt <= $requestedAt) {
            throw new InvalidArgumentException('An email-verification request must expire after it is created.');
        }
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function tokenHash(): EmailVerificationTokenHash
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
