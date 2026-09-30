<?php

declare(strict_types=1);

namespace Administration\Domain\Role\Event;

use Administration\Domain\Permission\ValueObject\PermissionName;
use Administration\Domain\Role\ValueObject\RoleId;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

final readonly class RolePermissionGranted extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        RoleId $id,
        int $version,
        DateTimeImmutable $at,
        private PermissionName $permission,
        ?CorrelationId $correlationId = null
    ) {
        parent::__construct($eventId, $id, $version, $at, $correlationId);
    }

    public static function eventName(): string
    {
        return 'administration.role_permission_granted';
    }

    public function payload(): array
    {
        return ['permission' => $this->permission->value];
    }
}
