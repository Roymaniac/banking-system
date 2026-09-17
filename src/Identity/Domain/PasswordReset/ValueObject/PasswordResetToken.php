<?php

declare(strict_types=1);

namespace Identity\Domain\PasswordReset\ValueObject;

use InvalidArgumentException;

/**
 * Holds the secret token sent to the user.
 *
 * This value must never be logged, serialized, or saved directly. Only its
 * one-way hash is allowed in persistent storage.
 */
final readonly class PasswordResetToken
{
    public function __construct(
        private string $value,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException('A password-reset token must be a 64-character hexadecimal value.');
        }
    }

    /**
     * The raw value is exposed only so a trusted notification adapter can put
     * it into the reset link sent to the user.
     */
    public function value(): string
    {
        return $this->value;
    }
}
