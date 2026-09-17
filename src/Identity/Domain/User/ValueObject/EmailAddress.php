<?php

declare(strict_types=1);

namespace Identity\Domain\User\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/**
 * Stores an email address in one consistent, validated form.
 */
final class EmailAddress extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The email address is not valid.');
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    /** @return array<string, string> */
    protected function toArray(): array
    {
        return ['value' => $this->value];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
