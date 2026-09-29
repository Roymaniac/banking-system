<?php

declare(strict_types=1);

namespace Administration\Domain\Department\Event;

use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Department\ValueObject\DepartmentName;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that an active department received a new display name. */
final readonly class DepartmentRenamed extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        DepartmentId $departmentId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        private DepartmentName $name,
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
        return 'administration.department_renamed';
    }

    public function payload(): array
    {
        return ['name' => $this->name->value];
    }
}
