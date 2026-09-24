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
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Ledger\Infrastructure\Persistence\DatabaseBalanceProjectionRepository;
use Ledger\Infrastructure\Persistence\DatabaseLedgerEntryRepository;
use Ledger\Infrastructure\Persistence\DatabaseLedgerRepository;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('binds the balance projection contract to its database adapter', function (): void {
    expect(app(BalanceProjectionRepository::class))->toBeInstanceOf(DatabaseBalanceProjectionRepository::class);
});

it('accumulates distinct entries while projecting each entry exactly once', function (): void {
    $now = new DateTimeImmutable('2026-09-20T09:00:00+01:00');
    $customerRepository = app(DatabaseCustomerRepository::class);
    $accountRepository = app(DatabaseAccountRepository::class);
    $ledgerRepository = app(DatabaseLedgerRepository::class);
    $ledgers = [];

    foreach (['2345678901', '3456789012'] as $index => $accountNumber) {
        $customer = Customer::create(
            CustomerId::generate(),
            UserId::generate(),
            new PersonalName($index === 0 ? 'Sender' : 'Receiver', null, 'Customer'),
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
        new EntryReference('transfer-balance-1'),
        new EntryDescription('Projected customer transfer'),
        $now,
        $now,
        Uuid::generate(),
    );
    $entryRepository = app(DatabaseLedgerEntryRepository::class);
    $entryRepository->save($entry);
    $entry->pullDomainEvents();
    $entry->addPosting(
        PostingId::generate(),
        $ledgers[0]->id(),
        PostingSide::Debit,
        new PostingAmount(5000, new LedgerCurrency('NGN')),
        $now,
        Uuid::generate(),
    );
    $entryRepository->save($entry);
    $entry->pullDomainEvents();
    $entry->addPosting(
        PostingId::generate(),
        $ledgers[1]->id(),
        PostingSide::Credit,
        new PostingAmount(5000, new LedgerCurrency('NGN')),
        $now,
        Uuid::generate(),
    );
    $entryRepository->save($entry);
    $entry->pullDomainEvents();
    $entry->post($now, Uuid::generate());
    $entryRepository->save($entry);

    $repository = app(DatabaseBalanceProjectionRepository::class);
    $repository->apply($entry, $now);
    $repository->apply($entry, $now); // Simulate redelivery of the same event.

    $secondEntry = LedgerEntry::draft(
        LedgerEntryId::generate(),
        $ledgers[1]->id(),
        new EntryReference('transfer-balance-2'),
        new EntryDescription('Second projected transfer'),
        $now,
        $now,
        Uuid::generate()
    );
    $entryRepository->save($secondEntry);
    $secondEntry->pullDomainEvents();
    $secondEntry->addPosting(
        PostingId::generate(),
        $ledgers[1]->id(),
        PostingSide::Debit,
        new PostingAmount(2000, new LedgerCurrency('NGN')),
        $now,
        Uuid::generate()
    );
    $entryRepository->save($secondEntry);
    $secondEntry->pullDomainEvents();
    $secondEntry->addPosting(
        PostingId::generate(),
        $ledgers[0]->id(),
        PostingSide::Credit,
        new PostingAmount(2000, new LedgerCurrency('NGN')),
        $now,
        Uuid::generate()
    );
    $entryRepository->save($secondEntry);
    $secondEntry->pullDomainEvents();
    $secondEntry->post($now, Uuid::generate());
    $entryRepository->save($secondEntry);
    $repository->apply($secondEntry, $now);

    $sender = $repository->find($ledgers[0]->id());
    $receiver = $repository->find($ledgers[1]->id());

    expect($sender?->debitMinorUnits())->toBe(5000)
        ->and($sender?->creditMinorUnits())->toBe(2000)
        ->and($sender?->balanceMinorUnits())->toBe(-3000)
        ->and($receiver?->debitMinorUnits())->toBe(2000)
        ->and($receiver?->creditMinorUnits())->toBe(5000)
        ->and($receiver?->balanceMinorUnits())->toBe(3000)
        ->and(DB::table('ledger_balance_contributions')->count())->toBe(4);
});
