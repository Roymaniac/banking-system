<?php

declare(strict_types=1);

namespace Customer\Application\Address;

use Customer\Domain\Customer\Address\ValueObject\CountryCode;
use Customer\Domain\Customer\Address\ValueObject\PostalAddress;
use Customer\Domain\Customer\Exception\CustomerNotFound;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Replaces the details of a customer address without changing its ID. */
final readonly class UpdateCustomerAddress
{
    public function __construct(
        private CustomerRepository $customers,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(UpdateCustomerAddressCommand $command): void
    {
        $customer = $this->customers->findById($command->customerId);

        if ($customer === null) {
            throw CustomerNotFound::create();
        }

        $customer->updateAddress(
            addressId: $command->addressId,
            type: $command->type,
            details: new PostalAddress(
                $command->lineOne,
                $command->lineTwo,
                $command->city,
                $command->stateOrRegion,
                $command->postalCode,
                new CountryCode($command->countryCode),
            ),
            updatedAt: $this->clock->now(),
            eventId: $this->uuidGenerator->generate(),
            correlationId: $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($customer): array {
            $this->customers->save($customer);

            return $customer->pullDomainEvents();
        });

        $this->events->publish($domainEvents);
    }
}
