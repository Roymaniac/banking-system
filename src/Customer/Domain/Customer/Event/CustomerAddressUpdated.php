<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Event;

use Customer\Domain\Customer\Address\ValueObject\AddressId;
use Customer\Domain\Customer\Address\ValueObject\AddressType;
use Customer\Domain\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that an existing customer address changed. */
final readonly class CustomerAddressUpdated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        CustomerId $customerId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        private AddressId $addressId,
        private AddressType $addressType,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $customerId, $aggregateVersion, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'customer.address.updated';
    }

    /** @return array{address_id: string, address_type: string} */
    public function payload(): array
    {
        return [
            'address_id' => $this->addressId->value(),
            'address_type' => $this->addressType->value,
        ];
    }
}
