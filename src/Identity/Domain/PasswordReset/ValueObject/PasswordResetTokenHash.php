<?php

declare(strict_types=1);

namespace Identity\Domain\PasswordReset\ValueObject;

use InvalidArgumentException;

/**
 * Stores the irreversible SHA-256 representation of a reset token.
 */
final readonly class PasswordResetTokenHash
{
    public function __construct(
        private string $value,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException('A password-reset token hash must be a 64-character hexadecimal value.');
        }
    }

    public static function fromToken(PasswordResetToken $token): self
    {
        return new self(hash('sha256', $token->value()));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
