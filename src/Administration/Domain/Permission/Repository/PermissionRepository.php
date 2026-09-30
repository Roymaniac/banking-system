<?php

declare(strict_types=1);

namespace Administration\Domain\Permission\Repository;

use Administration\Domain\Permission\PermissionDefinition;
use Administration\Domain\Permission\ValueObject\PermissionId;
use Administration\Domain\Permission\ValueObject\PermissionName;

interface PermissionRepository
{
    public function save(PermissionDefinition $permission): void;

    public function findById(PermissionId $id): ?PermissionDefinition;

    public function nameExists(PermissionName $name): bool;
}
