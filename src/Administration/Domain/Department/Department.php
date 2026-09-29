<?php

declare(strict_types=1);

namespace Administration\Domain\Department;

use Administration\Domain\Department\Event\DepartmentCreated;
use Administration\Domain\Department\Event\DepartmentDeactivated;
use Administration\Domain\Department\Event\DepartmentRenamed;
use Administration\Domain\Department\Exception\InactiveDepartmentCannotChange;
use Administration\Domain\Department\ValueObject\DepartmentCode;
use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Department\ValueObject\DepartmentName;
use Administration\Domain\Department\ValueObject\DepartmentStatus;
use DateTimeImmutable;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** A stable organizational unit to which bank staff can later be assigned. */
final class Department extends AggregateRoot
{
    private function __construct(
        private readonly DepartmentId $id,
        private readonly DepartmentCode $code,
        private DepartmentName $name,
        private readonly DateTimeImmutable $createdAt,
        private DepartmentStatus $status = DepartmentStatus::Active,
        private ?DateTimeImmutable $deactivatedAt = null,
    ) {}

    public static function create(
        DepartmentId $id,
        DepartmentCode $code,
        DepartmentName $name,
        DateTimeImmutable $createdAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): self {
        $department = new self($id, $code, $name, $createdAt);

        $department->record(
            new DepartmentCreated(
                $eventId,
                $id,
                $createdAt,
                $code,
                $name,
                $correlationId
            )
        );

        return $department;
    }

    public static function reconstitute(
        DepartmentId $id,
        DepartmentCode $code,
        DepartmentName $name,
        DateTimeImmutable $createdAt,
        DepartmentStatus $status,
        ?DateTimeImmutable $deactivatedAt,
        int $version
    ): self {
        $department = new self($id, $code, $name, $createdAt, $status, $deactivatedAt);
        $department->reconstituteAtVersion($version);

        return $department;
    }

    public function id(): DepartmentId
    {
        return $this->id;
    }

    public function code(): DepartmentCode
    {
        return $this->code;
    }

    public function name(): DepartmentName
    {
        return $this->name;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function status(): DepartmentStatus
    {
        return $this->status;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function rename(
        DepartmentName $name,
        DateTimeImmutable $renamedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): void {
        $this->guardActive();
        $this->name = $name;

        $this->record(
            new DepartmentRenamed(
                $eventId,
                $this->id,
                $this->version() + 1,
                $renamedAt,
                $name,
                $correlationId
            )
        );
    }

    public function deactivate(
        DateTimeImmutable $deactivatedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): void {
        $this->guardActive();
        $this->status = DepartmentStatus::Inactive;
        $this->deactivatedAt = $deactivatedAt;

        $this->record(
            new DepartmentDeactivated(
                $eventId,
                $this->id,
                $this->version() + 1,
                $deactivatedAt,
                $correlationId
            )
        );
    }

    private function guardActive(): void
    {
        if ($this->status !== DepartmentStatus::Active) {
            throw InactiveDepartmentCannotChange::create();
        }
    }
}
