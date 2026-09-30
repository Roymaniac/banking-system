<?php

declare(strict_types=1);

namespace Administration\Infrastructure\Persistence;

use Administration\Domain\Role\Repository\RoleAssignmentRepository;
use Administration\Domain\Role\ValueObject\RoleId;
use Administration\Domain\Staff\ValueObject\StaffId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseRoleAssignmentRepository implements RoleAssignmentRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function exists(RoleId $roleId, StaffId $staffId): bool
    {
        return $this->connection->table('staff_role_assignments')
            ->where('role_id', $roleId->value())
            ->where('staff_id', $staffId->value())
            ->exists();
    }

    public function add(
        RoleId $roleId,
        StaffId $staffId,
        DateTimeImmutable $assignedAt
    ): void {

        $this->connection->table('staff_role_assignments')
            ->insert([
                'role_id' => $roleId->value(),
                'staff_id' => $staffId->value(),
                'assigned_at' => $assignedAt->setTimezone(new DateTimeZone('UTC'))
            ]);
    }
}
