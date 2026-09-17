<?php

declare(strict_types=1);

namespace Identity\Domain\User\Event;

use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/**
 * Records that a user's password changed through the reset process.
 *
 * The event intentionally contains no password or password hash.
 */
final readonly class UserPasswordReset extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        UserId $userId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct(
            eventId: $eventId,
            aggregateId: $userId,
            aggregateVersion: $aggregateVersion,
            occurredOn: $occurredOn,
            correlationId: $correlationId,
        );
    }

    public static function eventName(): string
    {
        return 'identity.user.password-reset';
    }
}
