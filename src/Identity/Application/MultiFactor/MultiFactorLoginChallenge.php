<?php

declare(strict_types=1);

namespace Identity\Application\MultiFactor;

use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;

/** A sensitive action waiting for its second authentication factor. */
final readonly class MultiFactorLoginChallenge
{
    public function __construct(
        public UserId $userId,
        public string $deviceName,
        public MultiFactorChallengePurpose $purpose,
        public ?string $contextHash,
        public int $attempts,
        public DateTimeImmutable $expiresAt,
    ) {}
}
