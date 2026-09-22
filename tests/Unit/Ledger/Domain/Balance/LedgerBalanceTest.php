<?php

declare(strict_types=1);

use Ledger\Domain\Balance\LedgerBalance;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;

it('calculates a customer-ledger balance as credits minus debits', function (): void {
    $balance = new LedgerBalance(
        LedgerId::generate(),
        new LedgerCurrency('NGN'),
        2500,
        10000,
    );

    expect($balance->balanceMinorUnits())->toBe(7500)
        ->and($balance->isZero())->toBeFalse();
});

it('creates a zero balance for a new ledger', function (): void {
    $balance = LedgerBalance::zero(LedgerId::generate(), new LedgerCurrency('NGN'));

    expect($balance->debitMinorUnits())->toBe(0)
        ->and($balance->creditMinorUnits())->toBe(0)
        ->and($balance->balanceMinorUnits())->toBe(0)
        ->and($balance->isZero())->toBeTrue();
});

it('rejects negative debit or credit totals', function (): void {
    new LedgerBalance(LedgerId::generate(), new LedgerCurrency('NGN'), -1, 0);
})->throws(InvalidArgumentException::class, 'Projected debit and credit totals cannot be negative.');
