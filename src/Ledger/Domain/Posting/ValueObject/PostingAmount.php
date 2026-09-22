<?php

declare(strict_types=1);

namespace Ledger\Domain\Posting\ValueObject;

use InvalidArgumentException;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\ValueObject\ValueObject;

/**
 * Stores money as positive minor units, such as kobo or cents.
 * Integers avoid the rounding errors caused by floating-point arithmetic.
 */
final class PostingAmount extends ValueObject
{
    public function __construct(
        private readonly int $minorUnits,
        private readonly LedgerCurrency $currency,
    ) {
        if ($minorUnits <= 0) {
            throw new InvalidArgumentException('A posting amount must be greater than zero.');
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
