<?php

declare(strict_types=1);

namespace Administration\Infrastructure\Persistence;

use Administration\Domain\Permission\Repository\RolePermissionRepository;
use Administration\Domain\Permission\ValueObject\PermissionId;
use Administration\Domain\Role\ValueObject\RoleId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseRolePermissionRepository implements RolePermissionRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function exists(RoleId $roleId, PermissionId $permissionId): bool
    {
        return $this->connection->table('role_permission_assignments')
            ->where('role_id', $roleId->value())
            ->where('permission_id', $permissionId->value())
            ->exists();
    }

    public function grant(
        RoleId $roleId,
        PermissionId $permissionId,
        DateTimeImmutable $grantedAt
    ): void {

        $this->connection->table('role_permission_assignments')
            ->insert([
                'role_id' => $roleId->value(),
                'permission_id' => $permissionId->value(),
                'granted_at' => $grantedAt->setTimezone(new DateTimeZone('UTC'))
            ]);
    }

    public function revoke(RoleId $roleId, PermissionId $permissionId): void
    {
        $this->connection->table('role_permission_assignments')
            ->where('role_id', $roleId->value())
            ->where('permission_id', $permissionId->value())
            ->delete();
    }
}
