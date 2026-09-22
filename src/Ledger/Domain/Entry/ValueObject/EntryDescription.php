<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Stores a short, readable explanation of the financial event. */
final class EntryDescription extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        if ($this->value === '' || mb_strlen($this->value) > 255) {
            throw new InvalidArgumentException('An entry description must contain between 1 and 255 characters.');
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
