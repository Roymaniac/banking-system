<?php

declare(strict_types=1);

namespace Ledger\Domain\Balance;

use InvalidArgumentException;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;

/**
 * A fast read model derived only from permanently posted ledger entries.
 * For customer deposit ledgers, credits increase and debits decrease balance.
 */
final readonly class LedgerBalance
{
    public function __construct(
        private LedgerId $ledgerId,
        private LedgerCurrency $currency,
        private int $debitMinorUnits,
        private int $creditMinorUnits,
    ) {
        if ($debitMinorUnits < 0 || $creditMinorUnits < 0) {
            throw new InvalidArgumentException('Projected debit and credit totals cannot be negative.');
        }
    }

    public static function zero(LedgerId $ledgerId, LedgerCurrency $currency): self
    {
        return new self($ledgerId, $currency, 0, 0);
    }

    public function ledgerId(): LedgerId
    {
        return $this->ledgerId;
    }

    public function currency(): LedgerCurrency
    {
        return $this->currency;
    }

    public function debitMinorUnits(): int
    {
        return $this->debitMinorUnits;
    }

    public function creditMinorUnits(): int
    {
        return $this->creditMinorUnits;
    }

    public function balanceMinorUnits(): int
    {
        return $this->creditMinorUnits - $this->debitMinorUnits;
    }

    public function isZero(): bool
    {
        return $this->balanceMinorUnits() === 0;
    }
}
