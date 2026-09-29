<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\Event;

use Administration\Domain\Staff\ValueObject\StaffId;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that an employee is no longer active staff. */
final readonly class StaffDeactivated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        StaffId $staffId,
        int $version,
        DateTimeImmutable $occurredOn,
        ?CorrelationId $correlationId = null
    ) {
        parent::__construct(
            $eventId,
            $staffId,
            $version,
            $occurredOn,
            $correlationId
        );
    }

    public static function eventName(): string
    {
        return 'administration.staff_deactivated';
    }
}
