<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Event;

use Customer\Domain\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/**
 * Announces that a customer profile now exists for a verified user.
 */
final readonly class CustomerProfileCreated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        CustomerId $customerId,
        DateTimeImmutable $occurredOn,
        private UserId $userId,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $customerId, 1, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'customer.profile.created';
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    /** @return array{user_id: string} */
    public function payload(): array
    {
        // Legal names and birth dates are deliberately excluded from events
        // because events may be copied into logs and other systems.
        return ['user_id' => $this->userId->value()];
    }
}
