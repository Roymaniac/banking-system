<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Provides an idempotency reference supplied by the originating workflow. */
final class EntryReference extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = strtoupper(trim($value));

        if (preg_match('/^[A-Z0-9][A-Z0-9._:\/-]{0,99}$/', $this->value) !== 1) {
            throw new InvalidArgumentException('An entry reference must be 1 to 100 letters, numbers, or safe separators.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    /** @return array{value: string} */
    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
