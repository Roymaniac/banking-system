<?php

declare(strict_types=1);

namespace Administration\Domain\Permission\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Human-readable explanation of a permission. */
final class PermissionLabel extends ValueObject
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        if (mb_strlen($normalized) < 3 || mb_strlen($normalized) > 150) {
            throw new InvalidArgumentException('A permission label must contain between 3 and 150 characters.');
        }

        $this->value = $normalized;
    }

    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
