<?php

declare(strict_types=1);

namespace Identity\Application\Authentication;

use Identity\Domain\User\ValueObject\PasswordHash;

/**
 * Keeps the application independent of Laravel's password-hashing tools.
 */
interface PasswordHasher
{
    /**
     * Converts a plain-text password into a safe value for database storage.
     */
    public function hash(string $plainPassword): PasswordHash;

    /**
     * Checks a password without exposing how it is hashed.
     *
     * A null hash means the email was not found. Implementations must still do
     * a real hash comparison in that case so attackers cannot discover which
     * email addresses exist by measuring response times.
     */
    public function verify(string $plainPassword, ?PasswordHash $passwordHash): bool;

    /**
     * Signals that an old hash should be upgraded after a successful sign-in.
     */
    public function needsRehash(PasswordHash $passwordHash): bool;
}
