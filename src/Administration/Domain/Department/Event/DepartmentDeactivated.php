<?php

declare(strict_types=1);

namespace Administration\Domain\Department\Event;

use Administration\Domain\Department\ValueObject\DepartmentId;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that a department is no longer available for staff assignment. */
final readonly class DepartmentDeactivated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        DepartmentId $departmentId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct(
            $eventId,
            $departmentId,
            $aggregateVersion,
            $occurredOn,
            $correlationId
        );
    }

    public static function eventName(): string
    {
        return 'administration.department_deactivated';
    }
}
