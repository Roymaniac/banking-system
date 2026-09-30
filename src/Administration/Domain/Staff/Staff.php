<?php

declare(strict_types=1);

namespace Administration\Domain\Staff;

use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Staff\Event\StaffDeactivated;
use Administration\Domain\Staff\Event\StaffHired;
use Administration\Domain\Staff\Event\StaffTransferred;
use Administration\Domain\Staff\Exception\InactiveStaffCannotChange;
use Administration\Domain\Staff\ValueObject\EmployeeNumber;
use Administration\Domain\Staff\ValueObject\JobTitle;
use Administration\Domain\Staff\ValueObject\StaffId;
use Administration\Domain\Staff\ValueObject\StaffStatus;
use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Employment record linking a sign-in identity to an operational department. */
final class Staff extends AggregateRoot
{
    private function __construct(
        private readonly StaffId $id,
        private readonly UserId $userId,
        private readonly EmployeeNumber $employeeNumber,
        private DepartmentId $departmentId,
        private readonly JobTitle $jobTitle,
        private readonly DateTimeImmutable $hiredAt,
        private StaffStatus $status = StaffStatus::Active,
        private ?DateTimeImmutable $deactivatedAt = null
    ) {}

    public static function hire(
        StaffId $id,
        UserId $userId,
        EmployeeNumber $number,
        DepartmentId $departmentId,
        JobTitle $title,
        DateTimeImmutable $hiredAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): self {

        $staff = new self(
            $id,
            $userId,
            $number,
            $departmentId,
            $title,
            $hiredAt
        );

        $staff->record(
            new StaffHired(
                $eventId,
                $id,
                $hiredAt,
                $userId,
                $number,
                $departmentId,
                $title,
                $correlationId
            )
        );

        return $staff;
    }

    public static function reconstitute(
        StaffId $id,
        UserId $userId,
        EmployeeNumber $number,
        DepartmentId $departmentId,
        JobTitle $title,
        DateTimeImmutable $hiredAt,
        StaffStatus $status,
        ?DateTimeImmutable $deactivatedAt,
        int $version
    ): self {

        $staff = new self(
            $id,
            $userId,
            $number,
            $departmentId,
            $title,
            $hiredAt,
            $status,
            $deactivatedAt
        );

        $staff->reconstituteAtVersion($version);

        return $staff;
    }

    public function id(): StaffId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function employeeNumber(): EmployeeNumber
    {
        return $this->employeeNumber;
    }

    public function departmentId(): DepartmentId
    {
        return $this->departmentId;
    }

    public function jobTitle(): JobTitle
    {
        return $this->jobTitle;
    }

    public function hiredAt(): DateTimeImmutable
    {
        return $this->hiredAt;
    }

    public function status(): StaffStatus
    {
        return $this->status;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function transfer(
        DepartmentId $departmentId,
        DateTimeImmutable $transferredAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): void {

        $this->guardActive();
        $this->departmentId = $departmentId;
        $this->record(
            new StaffTransferred(
                $eventId,
                $this->id,
                $this->version() + 1,
                $transferredAt,
                $departmentId,
                $correlationId
            )
        );
    }

    public function deactivate(
        DateTimeImmutable $at,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): void {

        $this->guardActive();
        $this->status = StaffStatus::Inactive;
        $this->deactivatedAt = $at;
        $this->record(
            new StaffDeactivated(
                $eventId,
                $this->id,
                $this->version() + 1,
                $at,
                $correlationId
            )
        );
    }

    private function guardActive(): void
    {
        if ($this->status !== StaffStatus::Active) {
            throw InactiveStaffCannotChange::create();
        }
    }
}
