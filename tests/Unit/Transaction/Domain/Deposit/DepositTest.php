<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Deposit\Deposit;
use Transaction\Domain\Deposit\Event\DepositCompleted;

it('records an immutable completed deposit without exposing its amount in the payload', function (): void {
    $accountId = AccountId::generate();
    $entryId = LedgerEntryId::generate();
    $deposit = Deposit::complete(
        TransactionId::generate(),
        $accountId,
        $entryId,
        new TransactionReference('cash-deposit-1'),
        new TransactionAmount(15000, new LedgerCurrency('NGN')),
        new DateTimeImmutable('2026-09-20T10:00:00+01:00'),
        Uuid::generate(),
    );

    $event = $deposit->recordedEvents()[0];

    expect($deposit->reference()->value())->toBe('CASH-DEPOSIT-1')
        ->and($deposit->amount()->minorUnits())->toBe(15000)
        ->and($deposit->version())->toBe(1)
        ->and($event)->toBeInstanceOf(DepositCompleted::class)
        ->and($event->payload())->toBe([
            'account_id' => $accountId->value(),
            'ledger_entry_id' => $entryId->value(),
            'reference' => 'CASH-DEPOSIT-1',
            'currency' => 'NGN',
        ])
        ->and($event->payload())->not->toHaveKey('minor_units');
});

it('rejects a zero transaction amount', function (): void {
    new TransactionAmount(0, new LedgerCurrency('NGN'));
})->throws(InvalidArgumentException::class, 'A transaction amount must be greater than zero.');

it('rebuilds a stored deposit without recording another event', function (): void {
    $deposit = Deposit::reconstitute(
        TransactionId::generate(),
        AccountId::generate(),
        LedgerEntryId::generate(),
        new TransactionReference('cash-deposit-2'),
        new TransactionAmount(5000, new LedgerCurrency('NGN')),
        new DateTimeImmutable,
        1,
    );

    expect($deposit->recordedEvents())->toBeEmpty();
});
