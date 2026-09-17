<?php

declare(strict_types=1);

namespace Customer\Application\Address;

use Customer\Domain\Customer\Address\ValueObject\AddressId;
use Customer\Domain\Customer\Address\ValueObject\CountryCode;
use Customer\Domain\Customer\Address\ValueObject\PostalAddress;
use Customer\Domain\Customer\Exception\CustomerNotFound;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Adds a validated address to an existing customer profile. */
final readonly class AddCustomerAddress
{
    public function __construct(
        private CustomerRepository $customers,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(AddCustomerAddressCommand $command): AddressId
    {
        $customer = $this->customers->findById($command->customerId);

        if ($customer === null) {
            throw CustomerNotFound::create();
        }

        $addressId = new AddressId($this->uuidGenerator->generate()->value());
        $customer->addAddress(
            addressId: $addressId,
            type: $command->type,
            details: new PostalAddress(
                $command->lineOne,
                $command->lineTwo,
                $command->city,
                $command->stateOrRegion,
                $command->postalCode,
                new CountryCode($command->countryCode),
            ),
            addedAt: $this->clock->now(),
            eventId: $this->uuidGenerator->generate(),
            correlationId: $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($customer): array {
            $this->customers->save($customer);

            return $customer->pullDomainEvents();
        });

        // Notify other modules only after the updated profile is safely stored.
        $this->events->publish($domainEvents);

        return $addressId;
    }
}
