<?php

declare(strict_types=1);

namespace Account\Domain\Account\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Stores a three-letter currency code such as NGN, USD, or GBP. */
final class CurrencyCode extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = strtoupper(trim($value));

        if (preg_match('/^[A-Z]{3}$/', $this->value) !== 1) {
            throw new InvalidArgumentException('The currency code must contain exactly three letters.');
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
