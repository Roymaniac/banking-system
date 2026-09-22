<?php

declare(strict_types=1);

namespace Ledger\Domain\Ledger\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Locks a ledger to one three-letter currency for its entire lifetime. */
final class LedgerCurrency extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = strtoupper(trim($value));

        if (preg_match('/^[A-Z]{3}$/', $this->value) !== 1) {
            throw new InvalidArgumentException('The ledger currency must contain exactly three letters.');
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
