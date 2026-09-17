<?php

declare(strict_types=1);

namespace Identity\Domain\EmailVerification\ValueObject;

use InvalidArgumentException;

/**
 * Holds the secret token sent to the user's email address.
 *
 * The raw token is temporary and must never be logged or saved directly.
 */
final readonly class EmailVerificationToken
{
    public function __construct(
        private string $value,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException('An email-verification token must be a 64-character hexadecimal value.');
        }
    }

    /**
     * Exposed only so a trusted notifier can place it in the verification link.
     */
    public function value(): string
    {
        return $this->value;
    }
}
