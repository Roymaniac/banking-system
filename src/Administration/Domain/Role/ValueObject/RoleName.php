<?php

declare(strict_types=1);

namespace Administration\Domain\Role\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Stable machine-readable role name, such as transaction_approver. */
final class RoleName extends ValueObject
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (preg_match('/^[a-z][a-z0-9_]{2,49}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('A role name must be 3-50 lowercase letters, numbers, or underscores.');
        }

        $this->value = $normalized;
    }

    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
