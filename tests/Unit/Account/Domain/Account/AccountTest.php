<?php

declare(strict_types=1);

use Account\Domain\Account\Account;
use Account\Domain\Account\Event\AccountCreated;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Domain\Identifier\Uuid;

it('creates an account and records a privacy-safe domain event', function (): void {
    $customerId = CustomerId::generate();
    $account = Account::create(
        AccountId::generate(),
        $customerId,
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        Uuid::generate(),
    );

    $event = $account->recordedEvents()[0];

    expect($account->version())->toBe(1)
        ->and($account->customerId()->equals($customerId))->toBeTrue()
        ->and($event)->toBeInstanceOf(AccountCreated::class)
        ->and($event->payload())->toBe([
            'customer_id' => $customerId->value(),
            'account_type' => 'savings',
            'currency' => 'NGN',
        ]);
});

it('rebuilds a stored account without recording another event', function (): void {
    $account = Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Current,
        new CurrencyCode('USD'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        3,
    );

    expect($account->version())->toBe(3)
        ->and($account->recordedEvents())->toBeEmpty();
});
