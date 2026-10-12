<?php

declare(strict_types=1);

namespace Identity\Application\MultiFactor;

use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;

/** Persists MFA secrets, recovery codes, and short-lived login challenges. */
interface MultiFactorGateway
{
    public function isEnabled(UserId $userId): bool;

    public function replacePendingSetup(UserId $userId, string $secret, DateTimeImmutable $createdAt): void;

    public function secret(UserId $userId, bool $confirmedOnly = true): ?string;

    /** @param list<string> $recoveryCodeHashes */
    public function confirm(UserId $userId, array $recoveryCodeHashes, DateTimeImmutable $confirmedAt): void;

    /** @param list<string> $recoveryCodeHashes */
    public function replaceRecoveryCodes(
        UserId $userId,
        array $recoveryCodeHashes,
        DateTimeImmutable $replacedAt,
    ): void;

    public function disable(UserId $userId): void;

    public function consumeRecoveryCode(UserId $userId, string $code): bool;

    public function storeChallenge(
        string $tokenHash,
        UserId $userId,
        string $deviceName,
        MultiFactorChallengePurpose $purpose,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): void;

    public function challenge(string $tokenHash): ?MultiFactorLoginChallenge;

    public function recordFailedChallengeAttempt(string $tokenHash): void;

    public function consumeChallenge(string $tokenHash): void;
}
