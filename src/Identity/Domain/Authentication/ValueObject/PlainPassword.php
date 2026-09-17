<?php

declare(strict_types=1);

namespace Identity\Domain\Authentication\ValueObject;

use InvalidArgumentException;

/**
 * Temporarily holds a new password while enforcing the minimum safety policy.
 *
 * It cannot be serialized or converted to a string, which helps prevent an
 * accidental password leak into logs, API responses, or domain events.
 */
final readonly class PlainPassword
{
    public function __construct(
        private string $value,
    ) {
        $length = strlen($value);

        if ($length < 12 || $length > 128) {
            throw new InvalidArgumentException('A password must contain between 12 and 128 characters.');
        }
    }

    /**
     * Exposed only so the trusted password hasher can immediately hash it.
     */
    public function value(): string
    {
        return $this->value;
    }
}
