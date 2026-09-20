<?php

declare(strict_types=1);

namespace Transaction\Domain\Common\ValueObject;

use InvalidArgumentException;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\ValueObject\ValueObject;

/** Stores a positive transaction amount as integer minor units. */
final class TransactionAmount extends ValueObject
{
    public function __construct(
        private readonly int $minorUnits,
        private readonly LedgerCurrency $currency,
    ) {
        if ($minorUnits <= 0) {
            throw new InvalidArgumentException('A transaction amount must be greater than zero.');
        }
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function currency(): LedgerCurrency
    {
        return $this->currency;
    }

    /** @return array{minor_units: int, currency: string} */
    protected function toArray(): array
    {
        return [
            'minor_units' => $this->minorUnits,
            'currency' => $this->currency->value(),
        ];
    }
}
