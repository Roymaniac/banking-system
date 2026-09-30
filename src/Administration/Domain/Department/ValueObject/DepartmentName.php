<?php

declare(strict_types=1);

namespace Administration\Domain\Department\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** The readable department name shown to staff and administrators. */
final class DepartmentName extends ValueObject
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        $length = mb_strlen($normalized);

        if ($length < 2 || $length > 100) {
            throw new InvalidArgumentException('A department name must contain between 2 and 100 characters.');
        }

        $this->value = $normalized;
    }

    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
