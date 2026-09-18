<?php

declare(strict_types=1);

use Customer\Application\Contact\AddCustomerContact;
use Customer\Application\Contact\AddCustomerContactCommand;
use Customer\Application\Contact\UpdateCustomerContact;
use Customer\Application\Contact\UpdateCustomerContactCommand;
use Customer\Domain\Customer\Contact\ValueObject\ContactType;
use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Event\CustomerContactAdded;
use Customer\Domain\Customer\Event\CustomerContactUpdated;
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

final readonly class CustomerContactUseCaseClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-17T12:00:00+01:00');
    }
}

final class CustomerContactUseCaseTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class CustomerContactUseCasePublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function customerContactUseCaseCustomer(): Customer
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

it('adds and updates a contact through the application services', function (): void {
    $customer = customerContactUseCaseCustomer();
    $repository = Mockery::mock(CustomerRepository::class);
    $repository->shouldReceive('findById')->twice()->andReturn($customer);
    $repository->shouldReceive('save')->twice()->with($customer);
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->times(3)->andReturn(Uuid::generate(), Uuid::generate(), Uuid::generate());
    $publisher = new CustomerContactUseCasePublisher;
    $clock = new CustomerContactUseCaseClock;
    $transactions = new CustomerContactUseCaseTransactions;

    $contactId = (new AddCustomerContact($repository, $clock, $ids, $transactions, $publisher))
        ->handle(new AddCustomerContactCommand(
            $customer->id(),
            ContactType::Email,
            'Ada@Example.com',
        ));

    expect($customer->contacts()[0]->contactPoint()->value())->toBe('ada@example.com')
        ->and($publisher->published[0])->toBeInstanceOf(CustomerContactAdded::class);

    (new UpdateCustomerContact($repository, $clock, $ids, $transactions, $publisher))
        ->handle(new UpdateCustomerContactCommand(
            $customer->id(),
            $contactId,
            ContactType::Phone,
            '+234 801 234 5678',
        ));

    expect($customer->contacts()[0]->id()->equals($contactId))->toBeTrue()
        ->and($customer->contacts()[0]->contactPoint()->value())->toBe('+2348012345678')
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(CustomerContactUpdated::class);
});
