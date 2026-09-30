<?php

declare(strict_types=1);

namespace Administration\Application\Permission;

use Administration\Domain\Permission\Exception\PermissionNotFound;
use Administration\Domain\Permission\Exception\PermissionNotGranted;
use Administration\Domain\Permission\Repository\PermissionRepository;
use Administration\Domain\Permission\Repository\RolePermissionRepository;
use Administration\Domain\Permission\ValueObject\PermissionId;
use Administration\Domain\Role\Exception\RoleNotFound;
use Administration\Domain\Role\Repository\RoleRepository;
use Administration\Domain\Role\ValueObject\RoleId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\UuidGenerator;

/** Revokes a permission and records the role change for audit. */
final readonly class RevokePermissionFromRole
{
    public function __construct(
        private PermissionRepository $permissions,
        private RolePermissionRepository $grants,
        private RoleRepository $roles,
        private Clock $clock,
        private UuidGenerator $ids,
        private TransactionManager $transactions,
        private EventPublisher $events
    ) {}

    public function handle(
        RoleId $roleId,
        PermissionId $permissionId,
        ?CorrelationId $correlationId = null
    ): void {

        $events = $this->transactions->run(function () use (
            $roleId,
            $permissionId,
            $correlationId
        ): array {

            $role = $this->roles->findByIdForUpdate($roleId);

            if ($role === null) {
                throw RoleNotFound::create();
            }

            $permission = $this->permissions->findById($permissionId);

            if ($permission === null) {
                throw PermissionNotFound::create();
            }

            if (! $this->grants->exists($roleId, $permissionId)) {
                throw PermissionNotGranted::create();
            }

            $role->revokePermission(
                $permission->name(),
                $this->clock->now(),
                $this->ids->generate(),
                $correlationId
            );

            $this->roles->save($role);

            $this->grants->revoke($roleId, $permissionId);

            return $role->pullDomainEvents();
        });

        $this->events->publish($events);
    }
}
