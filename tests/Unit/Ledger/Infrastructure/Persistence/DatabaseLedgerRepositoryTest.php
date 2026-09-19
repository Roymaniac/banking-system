<?php

declare(strict_types=1);

use Account\Domain\Account\Account;
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
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Infrastructure\Persistence\DatabaseLedgerRepository;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('binds the ledger repository contract to its database adapter', function (): void {
    expect(app(LedgerRepository::class))->toBeInstanceOf(DatabaseLedgerRepository::class);
});

it('stores and retrieves a ledger by ledger and account ID', function (): void {
    $now = new DateTimeImmutable('2026-09-19T09:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', null, 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        Uuid::generate(),
    );
    app(DatabaseCustomerRepository::class)->save($customer);
    $account = Account::reconstitute(
        AccountId::generate(),
        $customer->id(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        $now,
        3,
        new AccountNumber('1234567890'),
        AccountStatus::Active,
    );
    app(DatabaseAccountRepository::class)->save($account);

    $ledger = Ledger::create(
        LedgerId::generate(),
        $account->id(),
        new LedgerCurrency('NGN'),
        $now,
        Uuid::generate(),
    );
    $repository = app(DatabaseLedgerRepository::class);
    $repository->save($ledger);
    $byId = $repository->findById($ledger->id());
    $byAccount = $repository->findByAccountId($account->id());

    expect($byId?->id()->equals($ledger->id()))->toBeTrue()
        ->and($byId?->currency()->value())->toBe('NGN')
        ->and($byId?->recordedEvents())->toBeEmpty()
        ->and($byAccount?->id()->equals($ledger->id()))->toBeTrue()
        ->and($repository->existsForAccount($account->id()))->toBeTrue();
});
