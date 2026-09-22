<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Domain\Ledger\Event\LedgerCreated;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Identifier\Uuid;

it('creates a ledger for one account and records its creation event', function (): void {
    $accountId = AccountId::generate();
    $ledger = Ledger::create(
        LedgerId::generate(),
        $accountId,
        new LedgerCurrency('ngn'),
        new DateTimeImmutable('2026-09-19T09:00:00+01:00'),
        Uuid::generate(),
    );

    $event = $ledger->recordedEvents()[0];

    expect($ledger->accountId()->equals($accountId))->toBeTrue()
        ->and($ledger->currency()->value())->toBe('NGN')
        ->and($ledger->version())->toBe(1)
        ->and($event)->toBeInstanceOf(LedgerCreated::class)
        ->and($event->payload())->toBe([
            'account_id' => $accountId->value(),
            'currency' => 'NGN',
        ]);
});

it('rebuilds a stored ledger without recording a new event', function (): void {
    $ledger = Ledger::reconstitute(
        LedgerId::generate(),
        AccountId::generate(),
        new LedgerCurrency('USD'),
        new DateTimeImmutable('2026-09-19T09:00:00+01:00'),
        4,
    );

    expect($ledger->version())->toBe(4)
        ->and($ledger->recordedEvents())->toBeEmpty();
});

it('rejects a malformed ledger currency', function (): void {
    new LedgerCurrency('naira');
})->throws(InvalidArgumentException::class, 'The ledger currency must contain exactly three letters.');
