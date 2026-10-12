<?php

declare(strict_types=1);

namespace Identity\Application\MultiFactor;

use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;

/** A password-verified login waiting for its second authentication factor. */
final readonly class MultiFactorLoginChallenge
{
    public function __construct(
        public UserId $userId,
        public string $deviceName,
        public int $attempts,
        public DateTimeImmutable $expiresAt,
    ) {}
}
