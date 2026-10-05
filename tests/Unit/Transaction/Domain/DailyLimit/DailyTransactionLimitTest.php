<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\DailyLimit\DailyTransactionLimit;
use Transaction\Domain\DailyLimit\Event\DailyTransactionLimitConfigured;
use Transaction\Domain\DailyLimit\Event\DailyTransactionLimitReduced;

it('records a privacy-safe daily limit configuration event', function (): void {
    $limit = DailyTransactionLimit::configure(
        AccountId::generate(),
        new LedgerCurrency('NGN'),
        100000,
        new DateTimeImmutable('2026-09-23T09:00:00+01:00'),
        Uuid::generate()
    );
    $event = $limit->recordedEvents()[0];

    expect($limit->maximumMinorUnits())->toBe(100000)
        ->and($event)->toBeInstanceOf(DailyTransactionLimitConfigured::class)
        ->and($event->maximumMinorUnits())->toBe(100000)
        ->and($event->payload())->not->toHaveKey('maximum_minor_units');
});

it('preserves the bank maximum when a customer lowers the effective limit', function (): void {
    $now = new DateTimeImmutable('2026-10-01T09:00:00+01:00');
    $limit = DailyTransactionLimit::reconstitute(
        AccountId::generate(),
        new LedgerCurrency('NGN'),
        100000,
        $now,
        1,
    );

    $limit->reduce(40000, $now, Uuid::generate());
    $event = $limit->recordedEvents()[0];

    expect($limit->maximumMinorUnits())->toBe(100000)
        ->and($limit->customerMaximumMinorUnits())->toBe(40000)
        ->and($limit->effectiveMaximumMinorUnits())->toBe(40000)
        ->and($event)->toBeInstanceOf(DailyTransactionLimitReduced::class);
});

it('does not let a customer raise their effective daily limit', function (): void {
    $now = new DateTimeImmutable('2026-10-01T09:00:00+01:00');
    $limit = DailyTransactionLimit::reconstitute(
        AccountId::generate(),
        new LedgerCurrency('NGN'),
        100000,
        $now,
        2,
        40000,
    );

    $limit->reduce(50000, $now, Uuid::generate());
})->throws(InvalidArgumentException::class, 'only reduce');
