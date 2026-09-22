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
use Ledger\Application\Creation\CreateLedger;
use Ledger\Application\Creation\CreateLedgerCommand;
use Ledger\Domain\Ledger\Event\LedgerCreated;
use Ledger\Domain\Ledger\Exception\AccountNotEligibleForLedger;
use Ledger\Domain\Ledger\Exception\LedgerAlreadyExists;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

final readonly class CreateLedgerTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-19T09:00:00+01:00');
    }
}

final class CreateLedgerTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class CreateLedgerTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function createLedgerTestAccount(AccountStatus $status = AccountStatus::Active): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        3,
        new AccountNumber('1234567890'),
        $status,
    );
}

it('creates and publishes a ledger for an active account', function (): void {
    $account = createLedgerTestAccount();
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->with($account->id())->andReturn($account);
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('existsForAccount')->once()->with($account->id())->andReturnFalse();
    $ledgers->shouldReceive('save')->once()->with(Mockery::type(Ledger::class));
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->twice()->andReturn(Uuid::generate(), Uuid::generate());
    $publisher = new CreateLedgerTestPublisher;

    $ledger = (new CreateLedger(
        $accounts,
        $ledgers,
        new CreateLedgerTestClock,
        $ids,
        new CreateLedgerTestTransactions,
        $publisher,
    ))->handle(new CreateLedgerCommand($account->id()));

    expect($ledger->accountId()->equals($account->id()))->toBeTrue()
        ->and($ledger->currency()->value())->toBe('NGN')
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(LedgerCreated::class);
});

it('rejects an account that is not active', function (): void {
    $account = createLedgerTestAccount(AccountStatus::Frozen);
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->andReturn($account);

    (new CreateLedger(
        $accounts,
        Mockery::mock(LedgerRepository::class),
        new CreateLedgerTestClock,
        Mockery::mock(UuidGenerator::class),
        new CreateLedgerTestTransactions,
        new CreateLedgerTestPublisher,
    ))->handle(new CreateLedgerCommand($account->id()));
})->throws(AccountNotEligibleForLedger::class, 'An active account is required');

it('prevents a second ledger for the same account', function (): void {
    $account = createLedgerTestAccount();
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->andReturn($account);
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('existsForAccount')->once()->andReturnTrue();

    (new CreateLedger(
        $accounts,
        $ledgers,
        new CreateLedgerTestClock,
        Mockery::mock(UuidGenerator::class),
        new CreateLedgerTestTransactions,
        new CreateLedgerTestPublisher,
    ))->handle(new CreateLedgerCommand($account->id()));
})->throws(LedgerAlreadyExists::class, 'A ledger already exists for this account.');
