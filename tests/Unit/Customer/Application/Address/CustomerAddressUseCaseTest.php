<?php

declare(strict_types=1);

use Customer\Application\Address\AddCustomerAddress;
use Customer\Application\Address\AddCustomerAddressCommand;
use Customer\Application\Address\UpdateCustomerAddress;
use Customer\Application\Address\UpdateCustomerAddressCommand;
use Customer\Domain\Customer\Address\ValueObject\AddressType;
use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Event\CustomerAddressAdded;
use Customer\Domain\Customer\Event\CustomerAddressUpdated;
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

final readonly class CustomerAddressUseCaseClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-17T12:00:00+01:00');
    }
}

final class CustomerAddressUseCaseTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class CustomerAddressUseCasePublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function customerAddressUseCaseCustomer(): Customer
{
    $now = new DateTimeImmutable('2026-09-17T10:00:00+01:00');

    return Customer::reconstitute(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', null, 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        1,
    );
}

it('adds an address, saves the customer, and publishes the event', function (): void {
    $customer = customerAddressUseCaseCustomer();
    $repository = Mockery::mock(CustomerRepository::class);
    $repository->shouldReceive('findById')->once()->with($customer->id())->andReturn($customer);
    $repository->shouldReceive('save')->once()->with($customer);
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->twice()->andReturn(Uuid::generate(), Uuid::generate());
    $publisher = new CustomerAddressUseCasePublisher;

    $addressId = (new AddCustomerAddress(
        $repository,
        new CustomerAddressUseCaseClock,
        $ids,
        new CustomerAddressUseCaseTransactions,
        $publisher,
    ))->handle(new AddCustomerAddressCommand(
        $customer->id(),
        AddressType::Residential,
        '14 Broad Street',
        null,
        'Lagos',
        'Lagos',
        '100001',
        'NG',
    ));

    expect($customer->addresses()[0]->id()->equals($addressId))->toBeTrue()
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(CustomerAddressAdded::class);
});

it('updates an address, saves the customer, and publishes the event', function (): void {
    $customer = customerAddressUseCaseCustomer();
    $repository = Mockery::mock(CustomerRepository::class);
    $repository->shouldReceive('findById')->twice()->andReturn($customer);
    $repository->shouldReceive('save')->twice()->with($customer);
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->times(3)->andReturn(Uuid::generate(), Uuid::generate(), Uuid::generate());
    $publisher = new CustomerAddressUseCasePublisher;
    $clock = new CustomerAddressUseCaseClock;
    $transactions = new CustomerAddressUseCaseTransactions;

    $addressId = (new AddCustomerAddress($repository, $clock, $ids, $transactions, $publisher))
        ->handle(new AddCustomerAddressCommand(
            $customer->id(),
            AddressType::Residential,
            '14 Broad Street',
            null,
            'Lagos Island',
            'Lagos',
            '100001',
            'NG',
        ));

    (new UpdateCustomerAddress($repository, $clock, $ids, $transactions, $publisher))
        ->handle(new UpdateCustomerAddressCommand(
            $customer->id(),
            $addressId,
            AddressType::Mailing,
            '22 Marina Road',
            null,
            'Lagos Island',
            'Lagos',
            '100002',
            'NG',
        ));

    expect($customer->addresses()[0]->details()->lineOne())->toBe('22 Marina Road')
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(CustomerAddressUpdated::class);
});
