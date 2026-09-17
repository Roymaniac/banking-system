<?php

declare(strict_types=1);

namespace Identity\Domain\EmailVerification\ValueObject;

use InvalidArgumentException;

/**
 * Stores the irreversible SHA-256 representation of a verification token.
 */
final readonly class EmailVerificationTokenHash
{
    public function __construct(
        private string $value,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException('An email-verification token hash must be a 64-character hexadecimal value.');
        }
    }

    public static function fromToken(EmailVerificationToken $token): self
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
