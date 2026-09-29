<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\Event;

use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Staff\ValueObject\StaffId;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that an active employee moved to another department. */
final readonly class StaffTransferred extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        StaffId $staffId,
        int $version,
        DateTimeImmutable $occurredOn,
        private DepartmentId $departmentId,
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
        return 'administration.staff_transferred';
    }

    public function payload(): array
    {
        return [
            'department_id' => $this->departmentId->value()
        ];
    }
}
