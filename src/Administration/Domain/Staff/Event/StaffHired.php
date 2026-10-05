<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\Event;

use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Staff\ValueObject\EmployeeNumber;
use Administration\Domain\Staff\ValueObject\JobTitle;
use Administration\Domain\Staff\ValueObject\StaffId;
use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that a person joined the bank's staff. */
final readonly class StaffHired extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        StaffId $staffId,
        DateTimeImmutable $occurredOn,
        private UserId $userId,
        private EmployeeNumber $employeeNumber,
        private DepartmentId $departmentId,
        private JobTitle $jobTitle,
        ?CorrelationId $correlationId = null
    ) {
        parent::__construct(
            $eventId,
            $staffId,
            1,
            $occurredOn,
            $correlationId
        );
    }

    public static function eventName(): string
    {
        return 'administration.staff_hired';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->userId->value(),
            'employee_number' => $this->employeeNumber->value,
            'department_id' => $this->departmentId->value(),
            'job_title' => $this->jobTitle->value,
        ];
    }
}
