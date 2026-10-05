<?php

declare(strict_types=1);

namespace Administration\Infrastructure\Persistence;

use Administration\Application\Directory\AdministrationDirectoryQuery;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/** Builds bounded, display-ready views for administration screens. */
final readonly class DatabaseAdministrationDirectoryQuery implements AdministrationDirectoryQuery
{
    public function __construct(private ConnectionInterface $connection) {}

    public function departments(
        int $page,
        int $perPage,
        ?string $search,
        ?string $status
    ): array {

        $query = $this->connection->table('departments');

        if ($search !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $this->paginate(
            $query->orderBy('name'),
            $page,
            $perPage,
            fn (object $row): array => [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'status' => $row->status,
                'created_at' => $this->date($row->created_at),
                'deactivated_at' => $this->nullableDate($row->deactivated_at),
            ]
        );
    }

    public function department(string $id): ?array
    {
        $row = $this->connection->table('departments')
            ->where('id', $id)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'code' => $row->code,
            'name' => $row->name,
            'status' => $row->status,
            'created_at' => $this->date($row->created_at),
            'deactivated_at' => $this->nullableDate($row->deactivated_at),
            'staff_count' => $this->connection->table('staff')->where('department_id', $id)->count(),
        ];
    }

    public function staff(
        int $page,
        int $perPage,
        ?string $search,
        ?string $status
    ): array {

        $query = $this->connection->table('staff')
            ->join('departments', 'departments.id', '=', 'staff.department_id')
            ->select('staff.*', 'departments.code as department_code', 'departments.name as department_name');

        if ($search !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('staff.employee_number', 'like', "%{$search}%")
                    ->orWhere('staff.job_title', 'like', "%{$search}%");
            });
        }
        if ($status !== null) {
            $query->where('staff.status', $status);
        }

        return $this->paginate(
            $query->orderBy('staff.employee_number'),
            $page,
            $perPage,
            fn (object $row): array => $this->staffRow($row)
        );
    }

    public function staffMember(string $id): ?array
    {
        $row = $this->connection->table('staff')
            ->join('departments', 'departments.id', '=', 'staff.department_id')
            ->select('staff.*', 'departments.code as department_code', 'departments.name as department_name')
            ->where('staff.id', $id)
            ->first();

        if ($row === null) {
            return null;
        }

        $staff = $this->staffRow($row);
        $staff['roles'] = $this->connection->table('staff_role_assignments')
            ->join('administration_roles', 'administration_roles.id', '=', 'staff_role_assignments.role_id')
            ->where('staff_role_assignments.staff_id', $id)
            ->orderBy('administration_roles.label')
            ->get([
                'administration_roles.id',
                'administration_roles.name',
                'administration_roles.label',
                'administration_roles.status',
            ])
            ->map(fn (object $role): array => (array) $role)
            ->all();

        return $staff;
    }

    public function roles(
        int $page,
        int $perPage,
        ?string $search,
        ?string $status
    ): array {

        $query = $this->connection->table('administration_roles');

        if ($search !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%");
            });
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $this->paginate(
            $query->orderBy('label'),
            $page,
            $perPage,
            fn (object $row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'label' => $row->label,
                'status' => $row->status,
                'created_at' => $this->date($row->created_at),
                'deactivated_at' => $this->nullableDate($row->deactivated_at),
            ]
        );
    }

    public function role(string $id): ?array
    {
        $row = $this->connection->table('administration_roles')
            ->where('id', $id)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'name' => $row->name,
            'label' => $row->label,
            'status' => $row->status,
            'created_at' => $this->date($row->created_at),
            'deactivated_at' => $this->nullableDate($row->deactivated_at),
            'permissions' => $this->connection->table('role_permission_assignments')
                ->join('administration_permissions', 'administration_permissions.id', '=', 'role_permission_assignments.permission_id')
                ->where('role_permission_assignments.role_id', $id)
                ->orderBy('administration_permissions.name')
                ->get([
                    'administration_permissions.id',
                    'administration_permissions.name',
                    'administration_permissions.label',
                ])
                ->map(fn (object $permission): array => (array) $permission)
                ->all(),
            'staff_count' => $this->connection->table('staff_role_assignments')->where('role_id', $id)->count(),
        ];
    }

    public function permissions(int $page, int $perPage, ?string $search): array
    {
        $query = $this->connection->table('administration_permissions');

        if ($search !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%");
            });
        }

        return $this->paginate(
            $query->orderBy('name'),
            $page,
            $perPage,
            fn (object $row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'label' => $row->label,
                'created_at' => $this->date($row->created_at),
            ]
        );
    }

    public function permission(string $id): ?array
    {
        $row = $this->connection->table('administration_permissions')
            ->where('id', $id)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'name' => $row->name,
            'label' => $row->label,
            'created_at' => $this->date($row->created_at),
            'roles' => $this->connection->table('role_permission_assignments')
                ->join('administration_roles', 'administration_roles.id', '=', 'role_permission_assignments.role_id')
                ->where('role_permission_assignments.permission_id', $id)
                ->orderBy('administration_roles.label')
                ->get([
                    'administration_roles.id',
                    'administration_roles.name',
                    'administration_roles.label',
                    'administration_roles.status',
                ])
                ->map(fn (object $role): array => (array) $role)
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function staffRow(object $row): array
    {
        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'employee_number' => $row->employee_number,
            'department' => [
                'id' => $row->department_id,
                'code' => $row->department_code,
                'name' => $row->department_name,
            ],
            'job_title' => $row->job_title,
            'status' => $row->status,
            'hired_at' => $this->date($row->hired_at),
            'deactivated_at' => $this->nullableDate($row->deactivated_at),
        ];
    }

    /**
     * @param  callable(object): array<string, mixed>  $transform
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    private function paginate(
        Builder $query,
        int $page,
        int $perPage,
        callable $transform
    ): array {

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get()
            ->map($transform)
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    private function date(string $value): string
    {
        return (new DateTimeImmutable($value))->format(DATE_ATOM);
    }

    private function nullableDate(?string $value): ?string
    {
        return $value === null ? null : $this->date($value);
    }
}
