<?php

declare(strict_types=1);

use Account\Application\Closure\AccountClosureBalanceChecker;
use Account\Application\Closure\CloseAccount;
use Account\Application\Closure\CloseAccountCommand;
use Account\Domain\Account\Account;
use Account\Domain\Account\Event\AccountClosed;
use Account\Domain\Account\Exception\AccountNotFound;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\ClosureReason;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

final readonly class CloseAccountTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-19T09:00:00+01:00');
    }
}

final class CloseAccountTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class CloseAccountTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function closeAccountUseCaseAccount(): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Current,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        3,
        new AccountNumber('1234567890'),
        AccountStatus::Active,
    );
}

it('closes, saves, and publishes an account', function (): void {
    $account = closeAccountUseCaseAccount();
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->with($account->id())->andReturn($account);
    $accounts->shouldReceive('save')->once()->with($account);
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->once()->andReturn(Uuid::generate());
    $publisher = new CloseAccountTestPublisher;
    $balanceChecker = Mockery::mock(AccountClosureBalanceChecker::class);
    $balanceChecker->shouldReceive('assertZeroBalance')->once()->with($account->id());

    (new CloseAccount(
        $accounts,
        $balanceChecker,
        new CloseAccountTestClock,
        $ids,
        new CloseAccountTestTransactions,
        $publisher,
    ))->handle(new CloseAccountCommand($account->id(), ClosureReason::CustomerRequest));

    expect($account->isClosed())->toBeTrue()
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(AccountClosed::class);
});

it('rejects an unknown account', function (): void {
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->andReturnNull();

    (new CloseAccount(
        $accounts,
        Mockery::mock(AccountClosureBalanceChecker::class),
        new CloseAccountTestClock,
        Mockery::mock(UuidGenerator::class),
        new CloseAccountTestTransactions,
        new CloseAccountTestPublisher,
    ))->handle(new CloseAccountCommand(AccountId::generate(), ClosureReason::BankDecision));
})->throws(AccountNotFound::class, 'The requested account does not exist.');
