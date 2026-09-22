<?php

declare(strict_types=1);

use Account\Domain\Account\Account;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Ledger\Domain\Entry\Event\LedgerEntryPosted;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;
use Transaction\Application\Deposit\MakeDeposit;
use Transaction\Application\Deposit\MakeDepositCommand;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Deposit\Deposit;
use Transaction\Domain\Deposit\Event\DepositCompleted;
use Transaction\Domain\Deposit\Exception\AccountNotEligibleForDeposit;
use Transaction\Domain\Deposit\Exception\DuplicateDepositReference;
use Transaction\Domain\Deposit\Repository\DepositRepository;

final readonly class MakeDepositTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-20T10:00:00+01:00');
    }
}

final class MakeDepositTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class MakeDepositTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function makeDepositTestAccount(AccountStatus $status = AccountStatus::Active): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-19T09:00:00+01:00'),
        3,
        new AccountNumber('1234567890'),
        $status,
    );
}

function makeDepositTestLedger(AccountId $accountId): Ledger
{
    return Ledger::reconstitute(
        LedgerId::generate(),
        $accountId,
        new LedgerCurrency('NGN'),
        new DateTimeImmutable('2026-09-19T10:00:00+01:00'),
        1,
    );
}

it('atomically posts balanced lines and completes the deposit', function (): void {
    $account = makeDepositTestAccount();
    $customerLedger = makeDepositTestLedger($account->id());
    $fundingLedger = makeDepositTestLedger(AccountId::generate());
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->with($account->id())->andReturn($account);
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findByAccountId')->once()->with($account->id())->andReturn($customerLedger);
    $ledgers->shouldReceive('findById')->once()->with($fundingLedger->id())->andReturn($fundingLedger);
    $entries = Mockery::mock(LedgerEntryRepository::class);
    $entries->shouldReceive('save')->once()->with(Mockery::type(LedgerEntry::class));
    $deposits = Mockery::mock(DepositRepository::class);
    $deposits->shouldReceive('referenceExists')->once()->with(Mockery::on(
        fn (TransactionReference $reference): bool => $reference->value() === 'CASH-1001',
    ))->andReturnFalse();
    $deposits->shouldReceive('save')->once()->with(Mockery::type(Deposit::class));
    $ids = Mockery::mock(UuidGenerator::class);
    $generatedIds = array_map(fn (): Uuid => Uuid::generate(), range(1, 9));
    $ids->shouldReceive('generate')->times(9)->andReturn(...$generatedIds);
    $publisher = new MakeDepositTestPublisher;

    $deposit = (new MakeDeposit(
        $accounts,
        $ledgers,
        $entries,
        $deposits,
        new MakeDepositTestClock,
        $ids,
        new MakeDepositTestTransactions,
        $publisher,
    ))->handle(new MakeDepositCommand(
        $account->id(),
        $fundingLedger->id(),
        25000,
        'cash-1001',
        new DateTimeImmutable('2026-09-20T09:55:00+01:00'),
    ));

    $postedEvent = collect($publisher->published)->first(
        fn (DomainEvent $event): bool => $event instanceof LedgerEntryPosted,
    );

    expect($deposit->amount()->minorUnits())->toBe(25000)
        ->and($publisher->published)->toHaveCount(5)
        ->and($publisher->published[4])->toBeInstanceOf(DepositCompleted::class)
        ->and($postedEvent)->toBeInstanceOf(LedgerEntryPosted::class)
        ->and($postedEvent?->totalMinorUnits())->toBe(25000);
});

it('rejects a frozen account before creating financial records', function (): void {
    $account = makeDepositTestAccount(AccountStatus::Frozen);
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->andReturn($account);

    (new MakeDeposit(
        $accounts,
        Mockery::mock(LedgerRepository::class),
        Mockery::mock(LedgerEntryRepository::class),
        Mockery::mock(DepositRepository::class),
        new MakeDepositTestClock,
        Mockery::mock(UuidGenerator::class),
        new MakeDepositTestTransactions,
        new MakeDepositTestPublisher,
    ))->handle(new MakeDepositCommand(
        $account->id(),
        LedgerId::generate(),
        1000,
        'cash-1002',
        new DateTimeImmutable('2026-09-20T09:55:00+01:00'),
    ));
})->throws(AccountNotEligibleForDeposit::class, 'An active account is required');

it('rejects a repeated deposit reference', function (): void {
    $account = makeDepositTestAccount();
    $customerLedger = makeDepositTestLedger($account->id());
    $fundingLedger = makeDepositTestLedger(AccountId::generate());
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->andReturn($account);
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('findByAccountId')->once()->andReturn($customerLedger);
    $ledgers->shouldReceive('findById')->once()->andReturn($fundingLedger);
    $deposits = Mockery::mock(DepositRepository::class);
    $deposits->shouldReceive('referenceExists')->once()->andReturnTrue();

    (new MakeDeposit(
        $accounts,
        $ledgers,
        Mockery::mock(LedgerEntryRepository::class),
        $deposits,
        new MakeDepositTestClock,
        Mockery::mock(UuidGenerator::class),
        new MakeDepositTestTransactions,
        new MakeDepositTestPublisher,
    ))->handle(new MakeDepositCommand(
        $account->id(),
        $fundingLedger->id(),
        1000,
        'cash-1001',
        new DateTimeImmutable('2026-09-20T09:55:00+01:00'),
    ));
})->throws(DuplicateDepositReference::class, 'already been completed');
