<?php

declare(strict_types=1);

use Customer\Application\Profile\CreateCustomerProfile;
use Customer\Application\Profile\CreateCustomerProfileCommand;
use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Event\CustomerProfileCreated;
use Customer\Domain\Customer\Exception\CustomerProfileAlreadyExists;
use Customer\Domain\Customer\Exception\UserNotEligibleForCustomerProfile;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\User;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\PasswordHash;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

final readonly class CustomerProfileTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-17T10:00:00+01:00');
    }
}

final class CustomerProfileTestTransactionManager implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class CustomerProfileTestEventPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function customerProfileTestUser(bool $verified = true): User
{
    return User::reconstitute(
        UserId::generate(),
        new EmailAddress('customer@example.com'),
        new PasswordHash(password_hash('password', PASSWORD_BCRYPT)),
        new DateTimeImmutable('2026-09-01T10:00:00+01:00'),
        1,
        $verified ? new DateTimeImmutable('2026-09-02T10:00:00+01:00') : null,
    );
}

function customerProfileCommand(UserId $userId): CreateCustomerProfileCommand
{
    return new CreateCustomerProfileCommand($userId, ' Ada ', null, ' Lovelace ', '2000-01-01');
}

it('creates and publishes a profile for a verified user', function (): void {
    $user = customerProfileTestUser();
    $users = Mockery::mock(UserRepository::class);
    $users->shouldReceive('findById')->once()->with($user->id())->andReturn($user);

    $customers = Mockery::mock(CustomerRepository::class);
    $customers->shouldReceive('existsForUser')->once()->with($user->id())->andReturnFalse();
    $customers->shouldReceive('save')->once()->with(Mockery::type(Customer::class));

    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->twice()->andReturn(Uuid::generate(), Uuid::generate());
    $publisher = new CustomerProfileTestEventPublisher;

    $customer = (new CreateCustomerProfile(
        $users,
        $customers,
        new CustomerProfileTestClock,
        $ids,
        new CustomerProfileTestTransactionManager,
        $publisher,
    ))->handle(customerProfileCommand($user->id()));

    expect($customer->name()->firstName())->toBe('Ada')
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(CustomerProfileCreated::class);
});

it('rejects an unverified user', function (): void {
    $user = customerProfileTestUser(false);
    $users = Mockery::mock(UserRepository::class);
    $users->shouldReceive('findById')->once()->andReturn($user);

    (new CreateCustomerProfile(
        $users,
        Mockery::mock(CustomerRepository::class),
        new CustomerProfileTestClock,
        Mockery::mock(UuidGenerator::class),
        new CustomerProfileTestTransactionManager,
        new CustomerProfileTestEventPublisher,
    ))->handle(customerProfileCommand($user->id()));
})->throws(UserNotEligibleForCustomerProfile::class, 'A verified user is required');

it('prevents a user from owning two customer profiles', function (): void {
    $user = customerProfileTestUser();
    $users = Mockery::mock(UserRepository::class);
    $users->shouldReceive('findById')->once()->andReturn($user);
    $customers = Mockery::mock(CustomerRepository::class);
    $customers->shouldReceive('existsForUser')->once()->andReturnTrue();

    (new CreateCustomerProfile(
        $users,
        $customers,
        new CustomerProfileTestClock,
        Mockery::mock(UuidGenerator::class),
        new CustomerProfileTestTransactionManager,
        new CustomerProfileTestEventPublisher,
    ))->handle(customerProfileCommand($user->id()));
})->throws(CustomerProfileAlreadyExists::class, 'A customer profile already exists');
