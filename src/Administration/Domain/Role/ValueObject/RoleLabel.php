<?php

declare(strict_types=1);

namespace Administration\Domain\Role\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Human-readable role label shown in administration screens. */
final class RoleLabel extends ValueObject
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        if (mb_strlen($normalized) < 3 || mb_strlen($normalized) > 100) {
            throw new InvalidArgumentException('A role label must contain between 3 and 100 characters.');
        }

        $this->value = $normalized;
    }

    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
