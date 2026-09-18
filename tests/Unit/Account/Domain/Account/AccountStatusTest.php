<?php

declare(strict_types=1);

use Account\Domain\Account\Account;
use Account\Domain\Account\Event\AccountActivated;
use Account\Domain\Account\Exception\AccountNumberRequired;
use Account\Domain\Account\Exception\InvalidAccountStatusTransition;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Domain\Identifier\Uuid;

function accountStatusTestAccount(?AccountNumber $number = null, AccountStatus $status = AccountStatus::Pending): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        $number === null ? 1 : 2,
        $number,
        $status,
    );
}

it('starts every newly created account in pending status', function (): void {
    $account = Account::create(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable,
        Uuid::generate(),
    );

    expect($account->status())->toBe(AccountStatus::Pending)
        ->and($account->isActive())->toBeFalse();
});

it('activates a numbered pending account and records the event', function (): void {
    $account = accountStatusTestAccount(new AccountNumber('1234567890'));
    $account->activate(new DateTimeImmutable, Uuid::generate());

    expect($account->status())->toBe(AccountStatus::Active)
        ->and($account->isActive())->toBeTrue()
        ->and($account->version())->toBe(3)
        ->and($account->recordedEvents()[0])->toBeInstanceOf(AccountActivated::class);
});

it('does not activate an account before a number is assigned', function (): void {
    accountStatusTestAccount()->activate(new DateTimeImmutable, Uuid::generate());
})->throws(AccountNumberRequired::class, 'An account number must be assigned');

it('does not activate an account that is already active', function (): void {
    accountStatusTestAccount(new AccountNumber('1234567890'), AccountStatus::Active)
        ->activate(new DateTimeImmutable, Uuid::generate());
})->throws(InvalidAccountStatusTransition::class, 'An account cannot move from active status to active status.');
