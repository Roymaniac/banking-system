<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Event;

use Customer\Domain\Customer\Contact\ValueObject\ContactId;
use Customer\Domain\Customer\Contact\ValueObject\ContactType;
use Customer\Domain\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that a new communication channel was added. */
final readonly class CustomerContactAdded extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        CustomerId $customerId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        private ContactId $contactId,
        private ContactType $contactType,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $customerId, $aggregateVersion, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'customer.contact.added';
    }

    /** @return array{contact_id: string, contact_type: string} */
    public function payload(): array
    {
        return [
            'contact_id' => $this->contactId->value(),
            'contact_type' => $this->contactType->value,
        ];
    }
}
