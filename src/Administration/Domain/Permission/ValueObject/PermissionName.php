<?php

declare(strict_types=1);

namespace Administration\Domain\Permission\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Stable dot-notation capability checked by application services. */
final class PermissionName extends ValueObject
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (preg_match('/^[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*$/', $normalized) !== 1) {
            throw new InvalidArgumentException('A permission must use dot notation, for example "accounts.view".');
        }

        $this->value = $normalized;
    }

    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
