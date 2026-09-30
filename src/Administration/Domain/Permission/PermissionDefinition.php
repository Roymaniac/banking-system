<?php

declare(strict_types=1);

namespace Administration\Domain\Permission;

use Administration\Domain\Permission\Event\PermissionCreated;
use Administration\Domain\Permission\ValueObject\PermissionId;
use Administration\Domain\Permission\ValueObject\PermissionLabel;
use Administration\Domain\Permission\ValueObject\PermissionName;
use DateTimeImmutable;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** One immutable capability available for assignment to roles. */
final class PermissionDefinition extends AggregateRoot
{
    private function __construct(
        private readonly PermissionId $id,
        private readonly PermissionName $name,
        private readonly PermissionLabel $label,
        private readonly DateTimeImmutable $createdAt
    ) {}

    public static function create(
        PermissionId $id,
        PermissionName $name,
        PermissionLabel $label,
        DateTimeImmutable $at,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): self {

        $permission = new self($id, $name, $label, $at);

        $permission->record(
            new PermissionCreated(
                $eventId,
                $id,
                $at,
                $name,
                $label,
                $correlationId
            )
        );

        return $permission;
    }

    public static function reconstitute(
        PermissionId $id,
        PermissionName $name,
        PermissionLabel $label,
        DateTimeImmutable $at,
        int $version
    ): self {

        $permission = new self($id, $name, $label, $at);

        $permission->reconstituteAtVersion($version);

        return $permission;
    }

    public function id(): PermissionId
    {
        return $this->id;
    }

    public function name(): PermissionName
    {
        return $this->name;
    }

    public function label(): PermissionLabel
    {
        return $this->label;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
