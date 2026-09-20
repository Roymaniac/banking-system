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
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Infrastructure\Persistence\DatabaseBalanceProjectionRepository;
use Ledger\Infrastructure\Persistence\DatabaseLedgerRepository;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;
use Transaction\Application\Deposit\MakeDeposit;
use Transaction\Application\Deposit\MakeDepositCommand;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Deposit\Repository\DepositRepository;
use Transaction\Infrastructure\Persistence\DatabaseDepositRepository;

uses(TestCase::class, RefreshDatabase::class);

it('binds the deposit repository contract to its database adapter', function (): void {
    expect(app(DepositRepository::class))->toBeInstanceOf(DatabaseDepositRepository::class);
});

it('atomically stores and projects a completed deposit', function (): void {
    $now = new DateTimeImmutable;
    $customerRepository = app(DatabaseCustomerRepository::class);
    $accountRepository = app(DatabaseAccountRepository::class);
    $ledgerRepository = app(DatabaseLedgerRepository::class);
    $accounts = [];
    $ledgers = [];

    foreach (['4567890123', '5678901234'] as $index => $number) {
        $customer = Customer::create(
            CustomerId::generate(),
            UserId::generate(),
            new PersonalName($index === 0 ? 'Customer' : 'Funding', null, 'Owner'),
            DateOfBirth::fromString('2000-01-01', $now),
            $now,
            Uuid::generate(),
        );
        $customerRepository->save($customer);
        $account = Account::reconstitute(
            AccountId::generate(),
            $customer->id(),
            AccountType::Savings,
            new CurrencyCode('NGN'),
            $now,
            3,
            new AccountNumber($number),
            AccountStatus::Active,
        );
        $accountRepository->save($account);
        $ledger = Ledger::create(
            LedgerId::generate(),
            $account->id(),
            new LedgerCurrency('NGN'),
            $now,
            Uuid::generate(),
        );
        $ledgerRepository->save($ledger);
        $accounts[] = $account;
        $ledgers[] = $ledger;
    }

    $deposit = app(MakeDeposit::class)->handle(new MakeDepositCommand(
        $accounts[0]->id(),
        $ledgers[1]->id(),
        40000,
        'integration-deposit-1',
        $now->modify('-1 second'),
    ));

    $storedDeposit = app(DatabaseDepositRepository::class)->findByReference(
        new TransactionReference('integration-deposit-1'),
    );
    $storedEntry = app(LedgerEntryRepository::class)->findById($deposit->ledgerEntryId());
    $balances = app(DatabaseBalanceProjectionRepository::class);

    expect($storedDeposit?->id()->equals($deposit->id()))->toBeTrue()
        ->and($storedDeposit?->recordedEvents())->toBeEmpty()
        ->and($storedEntry?->status())->toBe(EntryStatus::Posted)
        ->and($storedEntry?->postings())->toHaveCount(2)
        ->and($balances->find($ledgers[0]->id())?->balanceMinorUnits())->toBe(40000)
        ->and($balances->find($ledgers[1]->id())?->balanceMinorUnits())->toBe(-40000);
});
