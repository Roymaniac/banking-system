<?php

declare(strict_types=1);

namespace Customer\Application\Profile;

use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Exception\CustomerProfileAlreadyExists;
use Customer\Domain\Customer\Exception\UserNotEligibleForCustomerProfile;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use Identity\Domain\User\Repository\UserRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Creates the personal profile that connects a verified user to banking. */
final readonly class CreateCustomerProfile
{
    public function __construct(
        private UserRepository $users,
        private CustomerRepository $customers,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(CreateCustomerProfileCommand $command): Customer
    {
        $user = $this->users->findById($command->userId);

        if ($user === null || ! $user->isEmailVerified()) {
            throw UserNotEligibleForCustomerProfile::create();
        }

        if ($this->customers->existsForUser($command->userId)) {
            throw CustomerProfileAlreadyExists::create();
        }

        $now = $this->clock->now();
        $customer = Customer::create(
            id: new CustomerId($this->uuidGenerator->generate()->value()),
            userId: $command->userId,
            name: new PersonalName($command->firstName, $command->middleName, $command->lastName),
            dateOfBirth: DateOfBirth::fromString($command->dateOfBirth, $now),
            registeredAt: $now,
            eventId: $this->uuidGenerator->generate(),
            correlationId: $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($customer): array {
            $this->customers->save($customer);

            return $customer->pullDomainEvents();
        });

        // Publishing after the transaction prevents other parts of the system
        // from seeing a profile that failed to reach the database.
        $this->events->publish($domainEvents);

        return $customer;
    }
}
