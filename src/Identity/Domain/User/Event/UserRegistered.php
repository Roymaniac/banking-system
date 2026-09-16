<?php

declare(strict_types=1);

namespace Identity\Domain\User\Event;

use DateTimeImmutable;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/**
 * Records the business fact that a new sign-in identity was created.
 */
final readonly class UserRegistered extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        UserId $userId,
        DateTimeImmutable $occurredOn,
        private EmailAddress $email,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct(
            eventId: $eventId,
            aggregateId: $userId,
            aggregateVersion: 1,
            occurredOn: $occurredOn,
            correlationId: $correlationId,
        );
    }

    public static function eventName(): string
    {
        return 'identity.user.registered';
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    /** @return array{email: string} */
    public function payload(): array
    {
        // Only non-sensitive information may leave the aggregate in an event.
        return ['email' => $this->email->value()];
    }
}
