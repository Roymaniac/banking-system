<?php

declare(strict_types=1);

namespace Customer\Application\Contact;

use Customer\Domain\Customer\Contact\ValueObject\ContactId;
use Customer\Domain\Customer\Contact\ValueObject\ContactPoint;
use Customer\Domain\Customer\Exception\CustomerNotFound;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Adds a validated communication channel to a customer profile. */
final readonly class AddCustomerContact
{
    public function __construct(
        private CustomerRepository $customers,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(AddCustomerContactCommand $command): ContactId
    {
        $customer = $this->customers->findById($command->customerId);

        if ($customer === null) {
            throw CustomerNotFound::create();
        }

        $contactId = new ContactId($this->uuidGenerator->generate()->value());
        $customer->addContact(
            contactId: $contactId,
            contactPoint: new ContactPoint($command->type, $command->value),
            addedAt: $this->clock->now(),
            eventId: $this->uuidGenerator->generate(),
            correlationId: $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($customer): array {
            $this->customers->save($customer);

            return $customer->pullDomainEvents();
        });

        $this->events->publish($domainEvents);

        return $contactId;
    }
}
