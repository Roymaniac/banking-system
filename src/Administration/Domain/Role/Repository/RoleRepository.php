<?php

declare(strict_types=1);

namespace Administration\Domain\Role\Repository;

use Administration\Domain\Role\Role;
use Administration\Domain\Role\ValueObject\RoleId;
use Administration\Domain\Role\ValueObject\RoleName;

interface RoleRepository
{
    public function save(Role $role): void;

    public function findByIdForUpdate(RoleId $id): ?Role;

    public function nameExists(RoleName $name): bool;
}
