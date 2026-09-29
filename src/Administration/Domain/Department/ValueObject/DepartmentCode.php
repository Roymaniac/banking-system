<?php

declare(strict_types=1);

namespace Administration\Domain\Department\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** A short, stable code used to identify a department in operations. */
final class DepartmentCode extends ValueObject
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if (preg_match('/^[A-Z][A-Z0-9_]{1,19}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('A department code must be 2-20 letters, numbers, or underscores and start with a letter.');
        }

        $this->value = $normalized;
    }

    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
