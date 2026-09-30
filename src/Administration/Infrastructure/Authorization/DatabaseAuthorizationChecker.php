<?php

declare(strict_types=1);

namespace Administration\Infrastructure\Authorization;

use Administration\Domain\Role\ValueObject\RoleStatus;
use Administration\Domain\Staff\ValueObject\StaffStatus;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\ConnectionInterface;

/** Resolves UUID staff permissions through active role assignments. */
final readonly class DatabaseAuthorizationChecker implements AuthorizationChecker
{
    public function __construct(private ConnectionInterface $connection) {}

    public function allows(UserId $userId, Permission $permission): bool
    {
        return $this->connection->table('staff')
            ->join('staff_role_assignments', 'staff_role_assignments.staff_id', '=', 'staff.id')
            ->join('administration_roles', 'administration_roles.id', '=', 'staff_role_assignments.role_id')
            ->join('role_permission_assignments', 'role_permission_assignments.role_id', '=', 'administration_roles.id')
            ->join('administration_permissions', 'administration_permissions.id', '=', 'role_permission_assignments.permission_id')
            ->where('staff.user_id', $userId->value())
            ->where('staff.status', StaffStatus::Active)
            ->where('administration_roles.status', RoleStatus::Active)
            ->where('administration_permissions.name', $permission->value())
            ->exists();
    }
}
