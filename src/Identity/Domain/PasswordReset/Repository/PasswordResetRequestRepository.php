<?php

declare(strict_types=1);

namespace Identity\Domain\PasswordReset\Repository;

use Identity\Domain\PasswordReset\PasswordResetRequest;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetTokenHash;

/**
 * Stores reset requests without exposing database details to the domain.
 */
interface PasswordResetRequestRepository
{
    /**
     * Replaces any earlier request for the same email address. This ensures a
     * user has only one current reset link at a time.
     */
    public function replace(PasswordResetRequest $request): void;

    public function findByTokenHash(PasswordResetTokenHash $tokenHash): ?PasswordResetRequest;

    /**
     * Permanently invalidates a token after it is successfully used.
     */
    public function consume(PasswordResetTokenHash $tokenHash): void;
}
