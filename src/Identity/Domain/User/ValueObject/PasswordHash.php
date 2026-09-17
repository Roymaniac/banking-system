<?php

declare(strict_types=1);

namespace Identity\Domain\User\ValueObject;

use InvalidArgumentException;

/**
 * Holds a password that has already been securely hashed.
 *
 * This class intentionally does not extend the shared ValueObject because that
 * base class can be JSON-serialized. Password hashes must never be exposed in
 * API responses, logs, or domain-event payloads.
 */
final readonly class PasswordHash
{
    public function __construct(
        private string $value,
    ) {
        if (password_get_info($value)['algoName'] === 'unknown') {
            throw new InvalidArgumentException('A recognized password hash is required.');
        }
    }

    /**
     * The stored hash is exposed only so persistence and authentication
     * adapters can save or verify it. It must never be returned to a client.
     */
    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
