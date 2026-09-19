<?php

declare(strict_types=1);

use Account\Application\Opening\CreateAccount;
use Account\Application\Opening\CreateAccountCommand;
use Account\Domain\Account\Account;
use Account\Domain\Account\Event\AccountCreated;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountType;
use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Exception\CustomerNotFound;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

final readonly class CreateAccountTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-18T09:00:00+01:00');
    }
}

final class CreateAccountTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class CreateAccountTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function createAccountTestCustomer(): Customer
{
    $now = new DateTimeImmutable('2026-09-18T08:00:00+01:00');

    return Customer::reconstitute(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', null, 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        1,
    );
}

it('creates and publishes an account for an existing customer', function (): void {
    $customer = createAccountTestCustomer();
    $customers = Mockery::mock(CustomerRepository::class);
    $customers->shouldReceive('findById')->once()->with($customer->id())->andReturn($customer);
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('save')->once()->with(Mockery::type(Account::class));
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->twice()->andReturn(Uuid::generate(), Uuid::generate());
    $publisher = new CreateAccountTestPublisher;

    $account = (new CreateAccount(
        $customers,
        $accounts,
        new CreateAccountTestClock,
        $ids,
        new CreateAccountTestTransactions,
        $publisher,
    ))->handle(new CreateAccountCommand($customer->id(), AccountType::Savings, 'ngn'));

    expect($account->customerId()->equals($customer->id()))->toBeTrue()
        ->and($account->currency()->value())->toBe('NGN')
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(AccountCreated::class);
});

it('does not create an account for a missing customer', function (): void {
    $customers = Mockery::mock(CustomerRepository::class);
    $customers->shouldReceive('findById')->once()->andReturnNull();

    (new CreateAccount(
        $customers,
        Mockery::mock(AccountRepository::class),
        new CreateAccountTestClock,
        Mockery::mock(UuidGenerator::class),
        new CreateAccountTestTransactions,
        new CreateAccountTestPublisher,
    ))->handle(new CreateAccountCommand(CustomerId::generate(), AccountType::Savings, 'NGN'));
})->throws(CustomerNotFound::class, 'The requested customer does not exist.');
