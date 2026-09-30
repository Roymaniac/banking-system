<?php

declare(strict_types=1);

namespace Administration\Domain\Department\Event;

use Administration\Domain\Department\ValueObject\DepartmentCode;
use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Department\ValueObject\DepartmentName;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that an operational department was created. */
final readonly class DepartmentCreated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        DepartmentId $departmentId,
        DateTimeImmutable $occurredOn,
        private DepartmentCode $code,
        private DepartmentName $name,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct(
            $eventId,
            $departmentId,
            1,
            $occurredOn,
            $correlationId
        );
    }

    public static function eventName(): string
    {
        return 'administration.department_created';
    }

    public function payload(): array
    {
        return ['code' => $this->code->value, 'name' => $this->name->value];
    }
}
