<?php

declare(strict_types=1);

namespace Administration\Domain\Permission\Repository;

use Administration\Domain\Permission\ValueObject\PermissionId;
use Administration\Domain\Role\ValueObject\RoleId;
use DateTimeImmutable;

interface RolePermissionRepository
{
    public function exists(RoleId $roleId, PermissionId $permissionId): bool;

    public function grant(RoleId $roleId, PermissionId $permissionId, DateTimeImmutable $grantedAt): void;

    public function revoke(RoleId $roleId, PermissionId $permissionId): void;
}
