<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\DailyLimit\DailyTransactionLimit;
use Transaction\Domain\DailyLimit\Event\DailyTransactionLimitConfigured;

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
