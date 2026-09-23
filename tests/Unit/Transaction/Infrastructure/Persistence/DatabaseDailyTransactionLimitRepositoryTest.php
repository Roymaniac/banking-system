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
use Illuminate\Support\Facades\DB;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;
use Transaction\Domain\DailyLimit\DailyTransactionLimit;
use Transaction\Domain\DailyLimit\Exception\DailyTransactionLimitExceeded;
use Transaction\Domain\DailyLimit\Repository\DailyTransactionLimitRepository;
use Transaction\Infrastructure\Persistence\DatabaseDailyTransactionLimitRepository;

uses(TestCase::class, RefreshDatabase::class);

it('binds the daily limit contract to its database adapter', function (): void {
    expect(app(DailyTransactionLimitRepository::class))
        ->toBeInstanceOf(DatabaseDailyTransactionLimitRepository::class);
});

it('accumulates outgoing spending and starts fresh on the next banking day', function (): void {
    $now = new DateTimeImmutable('2026-09-23T09:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Daily', null, 'Limit'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        Uuid::generate()
    );
    app(DatabaseCustomerRepository::class)->save($customer);
    $account = Account::reconstitute(
        AccountId::generate(),
        $customer->id(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        $now,
        3,
        new AccountNumber('4234567890'),
        AccountStatus::Active
    );
    app(DatabaseAccountRepository::class)->save($account);
    $repository = app(DatabaseDailyTransactionLimitRepository::class);
    $currency = new LedgerCurrency('NGN');
    $repository->save(DailyTransactionLimit::configure($account->id(), $currency, 10000, $now, Uuid::generate()));

    DB::transaction(function () use ($repository, $account, $currency, $now): void {
        $repository->consume($account->id(), $currency, 4000, $now);
    });
    DB::transaction(function () use ($repository, $account, $currency, $now): void {
        $repository->consume($account->id(), $currency, 6000, $now);
    });

    expect(DB::table('daily_transaction_limit_usages')
        ->where('account_id', $account->id()->value())
        ->where('usage_date', '2026-09-23')
        ->value('used_minor_units'))
        ->toBe(10000);

    DB::transaction(function () use ($repository, $account, $currency, $now): void {
        $repository->consume($account->id(), $currency, 1, $now);
    });
})->throws(DailyTransactionLimitExceeded::class, 'exceed');

it('allows the full limit again on a later banking day', function (): void {
    $now = new DateTimeImmutable('2026-09-23T09:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Next', null, 'Day'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        Uuid::generate()
    );
    app(DatabaseCustomerRepository::class)->save($customer);
    $account = Account::reconstitute(
        AccountId::generate(),
        $customer->id(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        $now,
        3,
        new AccountNumber('5234567890'),
        AccountStatus::Active
    );
    app(DatabaseAccountRepository::class)->save($account);
    $repository = app(DatabaseDailyTransactionLimitRepository::class);
    $currency = new LedgerCurrency('NGN');
    $repository->save(DailyTransactionLimit::configure($account->id(), $currency, 5000, $now, Uuid::generate()));

    DB::transaction(fn() => $repository->consume($account->id(), $currency, 5000, $now));
    DB::transaction(fn() => $repository->consume($account->id(), $currency, 5000, $now->modify('+1 day')));

    expect(DB::table('daily_transaction_limit_usages')->where('account_id', $account->id()->value())->count())->toBe(2);
});
