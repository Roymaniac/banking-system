<?php

declare(strict_types=1);

namespace Account\Domain\Account\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Stores the ten-digit number customers use to identify an account. */
final class AccountNumber extends ValueObject
{
    public function __construct(private readonly string $value)
    {
        if (preg_match('/^[1-9][0-9]{9}$/', $value) !== 1) {
            throw new InvalidArgumentException('An account number must contain exactly 10 digits and cannot start with zero.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function lastFour(): string
    {
        return substr($this->value, -4);
    }

    /** @return array{value: string} */
    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
