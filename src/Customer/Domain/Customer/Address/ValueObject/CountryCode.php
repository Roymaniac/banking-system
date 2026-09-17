<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Address\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Stores the standard two-letter code used to identify a country. */
final class CountryCode extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = strtoupper(trim($value));

        if (preg_match('/^[A-Z]{2}$/', $this->value) !== 1) {
            throw new InvalidArgumentException('The country code must contain exactly two letters.');
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
