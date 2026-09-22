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
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Ledger\Infrastructure\Persistence\DatabaseLedgerEntryRepository;
use Ledger\Infrastructure\Persistence\DatabaseLedgerRepository;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('binds the ledger-entry repository contract to its database adapter', function (): void {
    expect(app(LedgerEntryRepository::class))->toBeInstanceOf(DatabaseLedgerEntryRepository::class);
});

it('stores and retrieves a draft by ID and reference', function (): void {
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
    app(DatabaseLedgerRepository::class)->save($ledger);

    $reference = new EntryReference('deposit-0001');
    $entry = LedgerEntry::draft(
        LedgerEntryId::generate(),
        $ledger->id(),
        $reference,
        new EntryDescription('Cash deposit'),
        new DateTimeImmutable('2026-09-19T08:55:00+01:00'),
        $now,
        Uuid::generate(),
    );
    $repository = app(DatabaseLedgerEntryRepository::class);
    $repository->save($entry);
    $byId = $repository->findById($entry->id());
    $byReference = $repository->findByReference($ledger->id(), $reference);

    expect($byId?->id()->equals($entry->id()))->toBeTrue()
        ->and($byId?->status())->toBe(EntryStatus::Draft)
        ->and($byId?->description()->value())->toBe('Cash deposit')
        ->and($byId?->recordedEvents())->toBeEmpty()
        ->and($byReference?->id()->equals($entry->id()))->toBeTrue()
        ->and($repository->referenceExists($ledger->id(), $reference))->toBeTrue();
});

it('stores balanced postings and the final posted status', function (): void {
    $now = new DateTimeImmutable('2026-09-19T09:00:00+01:00');
    $customerRepository = app(DatabaseCustomerRepository::class);
    $accountRepository = app(DatabaseAccountRepository::class);
    $ledgerRepository = app(DatabaseLedgerRepository::class);
    $ledgers = [];

    foreach (['1234567890', '9876543210'] as $index => $accountNumber) {
        $customer = Customer::create(
            CustomerId::generate(),
            UserId::generate(),
            new PersonalName($index === 0 ? 'Debit' : 'Credit', null, 'Customer'),
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
            new AccountNumber($accountNumber),
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
        $ledgers[] = $ledger;
    }

    $entry = LedgerEntry::draft(
        LedgerEntryId::generate(),
        $ledgers[0]->id(),
        new EntryReference('transfer-0001'),
        new EntryDescription('Customer transfer'),
        $now,
        $now,
        Uuid::generate(),
    );
    $repository = app(DatabaseLedgerEntryRepository::class);
    $repository->save($entry);
    $entry->pullDomainEvents();
    $entry->addPosting(
        PostingId::generate(),
        $ledgers[0]->id(),
        PostingSide::Debit,
        new PostingAmount(2500, new LedgerCurrency('NGN')),
        $now,
        Uuid::generate(),
    );
    $repository->save($entry);
    $entry->pullDomainEvents();
    $entry->addPosting(
        PostingId::generate(),
        $ledgers[1]->id(),
        PostingSide::Credit,
        new PostingAmount(2500, new LedgerCurrency('NGN')),
        $now,
        Uuid::generate(),
    );
    $repository->save($entry);
    $entry->pullDomainEvents();
    $entry->post($now, Uuid::generate());
    $repository->save($entry);

    $stored = $repository->findById($entry->id());

    expect($stored?->status())->toBe(EntryStatus::Posted)
        ->and($stored?->version())->toBe(4)
        ->and($stored?->postings())->toHaveCount(2)
        ->and($stored?->postings()[0]->amount()->minorUnits())->toBe(2500)
        ->and($stored?->recordedEvents())->toBeEmpty();
});
