<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Authentication;

use Identity\Application\Authentication\PasswordHasher;
use Identity\Domain\User\ValueObject\PasswordHash;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Adapts Laravel's configured password hasher to the Identity application.
 */
final readonly class LaravelPasswordHasher implements PasswordHasher
{
    /**
     * A valid throwaway hash used when no user exists. Comparing against it
     * reduces the timing difference between unknown emails and bad passwords.
     */
    private const DUMMY_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(
        private Hasher $hasher,
    ) {}

    public function hash(string $plainPassword): PasswordHash
    {
        return new PasswordHash($this->hasher->make($plainPassword));
    }

    public function verify(string $plainPassword, ?PasswordHash $passwordHash): bool
    {
        $hashToCheck = $passwordHash?->value() ?? self::DUMMY_HASH;
        $matches = $this->hasher->check($plainPassword, $hashToCheck);

        // A successful match against the dummy hash must never authenticate.
        return $passwordHash !== null && $matches;
    }

    public function needsRehash(PasswordHash $passwordHash): bool
    {
        return $this->hasher->needsRehash($passwordHash->value());
    }
}
