<?php

declare(strict_types=1);

namespace Administration\Domain\Role;

use Administration\Domain\Permission\ValueObject\PermissionName;
use Administration\Domain\Role\Event\RoleAssigned;
use Administration\Domain\Role\Event\RoleCreated;
use Administration\Domain\Role\Event\RoleDeactivated;
use Administration\Domain\Role\Event\RolePermissionGranted;
use Administration\Domain\Role\Event\RolePermissionRevoked;
use Administration\Domain\Role\Exception\InactiveRoleCannotChange;
use Administration\Domain\Role\ValueObject\RoleId;
use Administration\Domain\Role\ValueObject\RoleLabel;
use Administration\Domain\Role\ValueObject\RoleName;
use Administration\Domain\Role\ValueObject\RoleStatus;
use Administration\Domain\Staff\ValueObject\StaffId;
use DateTimeImmutable;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** A named bundle that will receive permissions in the next phase. */
final class Role extends AggregateRoot
{
    private function __construct(
        private readonly RoleId $id,
        private readonly RoleName $name,
        private readonly RoleLabel $label,
        private readonly DateTimeImmutable $createdAt,
        private RoleStatus $status = RoleStatus::Active,
        private ?DateTimeImmutable $deactivatedAt = null
    ) {}

    public static function create(
        RoleId $id,
        RoleName $name,
        RoleLabel $label,
        DateTimeImmutable $at,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): self {

        $role = new self(
            $id,
            $name,
            $label,
            $at
        );

        $role->record(
            new RoleCreated(
                $eventId,
                $id,
                $at,
                $name,
                $label,
                $correlationId
            )
        );

        return $role;
    }

    public static function reconstitute(
        RoleId $id,
        RoleName $name,
        RoleLabel $label,
        DateTimeImmutable $createdAt,
        RoleStatus $status,
        ?DateTimeImmutable $deactivatedAt,
        int $version
    ): self {

        $role = new self(
            $id,
            $name,
            $label,
            $createdAt,
            $status,
            $deactivatedAt
        );

        $role->reconstituteAtVersion($version);

        return $role;
    }

    public function id(): RoleId
    {
        return $this->id;
    }

    public function name(): RoleName
    {
        return $this->name;
    }

    public function label(): RoleLabel
    {
        return $this->label;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function status(): RoleStatus
    {
        return $this->status;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function recordAssignment(
        StaffId $staffId,
        DateTimeImmutable $at,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): void {

        $this->guardActive();

        $this->record(
            new RoleAssigned(
                $eventId,
                $this->id,
                $this->version() + 1,
                $at,
                $staffId,
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

        $this->status = RoleStatus::Inactive;

        $this->deactivatedAt = $at;

        $this->record(
            new RoleDeactivated(
                $eventId,
                $this->id,
                $this->version() + 1,
                $at,
                $correlationId
            )
        );
    }

    public function grantPermission(
        PermissionName $permission,
        DateTimeImmutable $at,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): void {

        $this->guardActive();
        $this->record(
            new RolePermissionGranted(
                $eventId,
                $this->id,
                $this->version() + 1,
                $at,
                $permission,
                $correlationId
            )
        );
    }

    public function revokePermission(
        PermissionName $permission,
        DateTimeImmutable $at,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): void {

        $this->guardActive();
        $this->record(
            new RolePermissionRevoked(
                $eventId,
                $this->id,
                $this->version() + 1,
                $at,
                $permission,
                $correlationId
            )
        );
    }

    private function guardActive(): void
    {
        if ($this->status !== RoleStatus::Active) {
            throw InactiveRoleCannotChange::create();
        }
    }
}
