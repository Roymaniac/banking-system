<?php

declare(strict_types=1);

use Account\Domain\Account\Account;
use Account\Domain\Account\Event\AccountNumberAssigned;
use Account\Domain\Account\Exception\AccountNumberAlreadyAssigned;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Domain\Identifier\Uuid;

function accountNumberTestAccount(): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        1,
    );
}

it('assigns a number and records an event containing only the last four digits', function (): void {
    $account = accountNumberTestAccount();
    $account->assignNumber(
        new AccountNumber('1234567890'),
        new DateTimeImmutable('2026-09-18T10:00:00+01:00'),
        Uuid::generate(),
    );

    $event = $account->recordedEvents()[0];

    expect($account->number()?->value())->toBe('1234567890')
        ->and($account->version())->toBe(2)
        ->and($event)->toBeInstanceOf(AccountNumberAssigned::class)
        ->and($event->payload())->toBe(['last_four' => '7890']);
});

it('never allows an assigned account number to be replaced', function (): void {
    $account = accountNumberTestAccount();
    $account->assignNumber(new AccountNumber('1234567890'), new DateTimeImmutable, Uuid::generate());
    $account->assignNumber(new AccountNumber('9876543210'), new DateTimeImmutable, Uuid::generate());
})->throws(AccountNumberAlreadyAssigned::class, 'This account already has an account number.');
