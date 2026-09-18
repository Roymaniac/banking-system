<?php

declare(strict_types=1);

use Account\Application\Number\AccountNumberGenerator;
use Account\Application\Number\AssignAccountNumber;
use Account\Application\Number\AssignAccountNumberCommand;
use Account\Domain\Account\Account;
use Account\Domain\Account\Event\AccountNumberAssigned;
use Account\Domain\Account\Exception\AccountNotFound;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

final readonly class AssignNumberTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-18T10:00:00+01:00');
    }
}

final class AssignNumberTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class AssignNumberTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function assignNumberTestAccount(): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        1,
    );
}

it('skips a collision and assigns the next available number', function (): void {
    $account = assignNumberTestAccount();
    $collision = new AccountNumber('1234567890');
    $available = new AccountNumber('9876543210');
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->with($account->id())->andReturn($account);
    $accounts->shouldReceive('numberExists')->once()->with($collision)->andReturnTrue();
    $accounts->shouldReceive('numberExists')->once()->with($available)->andReturnFalse();
    $accounts->shouldReceive('save')->once()->with($account);
    $generator = Mockery::mock(AccountNumberGenerator::class);
    $generator->shouldReceive('generate')->twice()->andReturn($collision, $available);
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->once()->andReturn(Uuid::generate());
    $publisher = new AssignNumberTestPublisher;

    $number = (new AssignAccountNumber(
        $accounts,
        $generator,
        new AssignNumberTestClock,
        $ids,
        new AssignNumberTestTransactions,
        $publisher,
    ))->handle(new AssignAccountNumberCommand($account->id()));

    expect($number->equals($available))->toBeTrue()
        ->and($account->number()?->equals($available))->toBeTrue()
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(AccountNumberAssigned::class);
});

it('rejects an unknown account', function (): void {
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->once()->andReturnNull();

    (new AssignAccountNumber(
        $accounts,
        Mockery::mock(AccountNumberGenerator::class),
        new AssignNumberTestClock,
        Mockery::mock(UuidGenerator::class),
        new AssignNumberTestTransactions,
        new AssignNumberTestPublisher,
    ))->handle(new AssignAccountNumberCommand(AccountId::generate()));
})->throws(AccountNotFound::class, 'The requested account does not exist.');
