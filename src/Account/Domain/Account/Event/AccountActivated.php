<?php

declare(strict_types=1);

namespace Account\Domain\Account\Event;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that a fully provisioned account is ready for use. */
final readonly class AccountActivated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        AccountId $accountId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $accountId, $aggregateVersion, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'account.activated';
    }
}
