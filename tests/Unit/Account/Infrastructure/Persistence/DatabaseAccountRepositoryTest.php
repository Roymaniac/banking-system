<?php

declare(strict_types=1);

use Account\Domain\Account\Account;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Account\Infrastructure\Persistence\DatabaseAccountRepository;
use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use Customer\Infrastructure\Persistence\DatabaseCustomerRepository;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('binds the account repository contract to its database adapter', function (): void {
    expect(app(AccountRepository::class))->toBeInstanceOf(DatabaseAccountRepository::class);
});

it('stores and retrieves accounts by account and customer ID', function (): void {
    $now = new DateTimeImmutable('2026-09-18T09:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', null, 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        Uuid::generate(),
    );
    app(DatabaseCustomerRepository::class)->save($customer);

    $account = Account::create(
        AccountId::generate(),
        $customer->id(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        $now,
        Uuid::generate(),
    );
    $repository = app(DatabaseAccountRepository::class);
    $repository->save($account);

    $byId = $repository->findById($account->id());
    $forCustomer = $repository->findByCustomerId($customer->id());

    expect($byId?->id()->equals($account->id()))->toBeTrue()
        ->and($byId?->type())->toBe(AccountType::Savings)
        ->and($byId?->currency()->value())->toBe('NGN')
        ->and($byId?->recordedEvents())->toBeEmpty()
        ->and($forCustomer)->toHaveCount(1)
        ->and($forCustomer[0]->id()->equals($account->id()))->toBeTrue();
});

it('stores an assigned number and finds the account by that number', function (): void {
    $now = new DateTimeImmutable('2026-09-18T09:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Grace', null, 'Hopper'),
        DateOfBirth::fromString('1990-01-01', $now),
        $now,
        Uuid::generate(),
    );
    app(DatabaseCustomerRepository::class)->save($customer);

    $account = Account::create(
        AccountId::generate(),
        $customer->id(),
        AccountType::Current,
        new CurrencyCode('USD'),
        $now,
        Uuid::generate(),
    );
    $repository = app(DatabaseAccountRepository::class);
    $repository->save($account);
    $account->pullDomainEvents();

    $number = new AccountNumber('1234567890');
    $account->assignNumber($number, $now, Uuid::generate());
    $repository->save($account);

    $stored = $repository->findByNumber($number);

    expect($repository->numberExists($number))->toBeTrue()
        ->and($stored?->id()->equals($account->id()))->toBeTrue()
        ->and($stored?->number()?->equals($number))->toBeTrue()
        ->and($stored?->version())->toBe(2)
        ->and($stored?->recordedEvents())->toBeEmpty();
});

it('persists account status changes', function (): void {
    $now = new DateTimeImmutable('2026-09-18T09:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Katherine', null, 'Johnson'),
        DateOfBirth::fromString('1990-01-01', $now),
        $now,
        Uuid::generate(),
    );
    app(DatabaseCustomerRepository::class)->save($customer);

    $account = Account::create(
        AccountId::generate(),
        $customer->id(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        $now,
        Uuid::generate(),
    );
    $repository = app(DatabaseAccountRepository::class);
    $repository->save($account);
    $account->pullDomainEvents();
    $account->assignNumber(new AccountNumber('9876543210'), $now, Uuid::generate());
    $repository->save($account);
    $account->pullDomainEvents();
    $account->activate($now, Uuid::generate());
    $repository->save($account);

    $stored = $repository->findById($account->id());

    expect($stored?->status())->toBe(AccountStatus::Active)
        ->and($stored?->isActive())->toBeTrue()
        ->and($stored?->version())->toBe(3)
        ->and($stored?->recordedEvents())->toBeEmpty();
});
