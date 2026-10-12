<?php

declare(strict_types=1);

namespace Administration\Infrastructure\Authorization;

use Administration\Application\Security\OperatorContextQuery;
use Administration\Domain\Role\ValueObject\RoleStatus;
use Administration\Domain\Staff\ValueObject\StaffStatus;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\ConnectionInterface;

/** Reads only the active roles and permissions the operations portal may display. */
final readonly class DatabaseOperatorContextQuery implements OperatorContextQuery
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forUser(UserId $userId): ?array
    {
        $staff = $this->connection->table('staff')
            ->join('departments', 'departments.id', '=', 'staff.department_id')
            ->where('staff.user_id', $userId->value())
            ->where('staff.status', StaffStatus::Active->value)
            ->first([
                'staff.id',
                'staff.employee_number',
                'staff.job_title',
                'departments.id as department_id',
                'departments.code as department_code',
                'departments.name as department_name',
            ]);

        if ($staff === null) {
            return null;
        }

        $roles = $this->connection->table('staff_role_assignments')
            ->join('administration_roles', 'administration_roles.id', '=', 'staff_role_assignments.role_id')
            ->where('staff_role_assignments.staff_id', $staff->id)
            ->where('administration_roles.status', RoleStatus::Active->value)
            ->orderBy('administration_roles.label')
            ->get(['administration_roles.id', 'administration_roles.name', 'administration_roles.label'])
            ->map(static fn (object $role): array => (array) $role)
            ->all();

        $permissions = $this->connection->table('staff_role_assignments')
            ->join('administration_roles', 'administration_roles.id', '=', 'staff_role_assignments.role_id')
            ->join('role_permission_assignments', 'role_permission_assignments.role_id', '=', 'administration_roles.id')
            ->join('administration_permissions', 'administration_permissions.id', '=', 'role_permission_assignments.permission_id')
            ->where('staff_role_assignments.staff_id', $staff->id)
            ->where('administration_roles.status', RoleStatus::Active->value)
            ->orderBy('administration_permissions.name')
            ->distinct()
            ->pluck('administration_permissions.name')
            ->map(static fn (mixed $permission): string => (string) $permission)
            ->all();

        return [
            'id' => $staff->id,
            'employee_number' => $staff->employee_number,
            'job_title' => $staff->job_title,
            'department' => [
                'id' => $staff->department_id,
                'code' => $staff->department_code,
                'name' => $staff->department_name,
            ],
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }
}
