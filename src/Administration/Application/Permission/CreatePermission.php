<?php

declare(strict_types=1);

namespace Administration\Application\Permission;

use Administration\Domain\Permission\Exception\PermissionNameAlreadyExists;
use Administration\Domain\Permission\PermissionDefinition;
use Administration\Domain\Permission\Repository\PermissionRepository;
use Administration\Domain\Permission\ValueObject\PermissionId;
use Administration\Domain\Permission\ValueObject\PermissionLabel;
use Administration\Domain\Permission\ValueObject\PermissionName;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\UuidGenerator;

/** Adds one validated capability to the permission catalogue. */
final readonly class CreatePermission
{
    public function __construct(
        private PermissionRepository $permissions,
        private Clock $clock,
        private UuidGenerator $ids,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(
        string $name,
        string $label,
        ?CorrelationId $correlationId = null
    ): PermissionDefinition {

        $permissionName = new PermissionName($name);

        if ($this->permissions->nameExists($permissionName)) {
            throw PermissionNameAlreadyExists::create();
        }

        $permission = PermissionDefinition::create(
            new PermissionId($this->ids->generate()->value()),
            $permissionName,
            new PermissionLabel($label),
            $this->clock->now(),
            $this->ids->generate(),
            $correlationId
        );

        $events = $this->transactions->run(function () use ($permission): array {
            $this->permissions->save($permission);

            return $permission->pullDomainEvents();
        });

        $this->events->publish($events);

        return $permission;
    }
}
