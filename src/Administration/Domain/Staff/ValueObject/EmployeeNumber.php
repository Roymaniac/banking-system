<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Stable number used to identify an employee in bank operations. */
final class EmployeeNumber extends ValueObject
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if (preg_match('/^[A-Z0-9-]{3,20}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('An employee number must be 3-20 letters, numbers, or hyphens.');
        }

        $this->value = $normalized;
    }

    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
