<?php

declare(strict_types=1);

namespace Identity\Application\Session\View;

use DateTimeImmutable;

/** A safe view of one API session; the stored token hash is never included. */
final readonly class UserSessionView
{
    /** @param list<string> $abilities */
    public function __construct(
        public int $id,
        public string $deviceName,
        public array $abilities,
        public bool $isCurrent,
        public ?DateTimeImmutable $lastUsedAt,
        public ?DateTimeImmutable $expiresAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
