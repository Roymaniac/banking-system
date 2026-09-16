<?php

declare(strict_types=1);

namespace Identity\Domain\Authorization\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/**
 * Names one action a user may be allowed to perform.
 *
 * Permissions use readable dot notation such as "accounts.view" or
 * "transfers.approve". Application code checks permissions rather than role
 * names, allowing administrators to change role contents without code changes.
 */
final class Permission extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (preg_match('/^[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*$/', $normalized) !== 1) {
            throw new InvalidArgumentException(
                'A permission must use dot notation, for example "accounts.view".',
            );
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
