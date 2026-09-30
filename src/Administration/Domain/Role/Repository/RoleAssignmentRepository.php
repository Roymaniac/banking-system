<?php

declare(strict_types=1);

namespace Administration\Domain\Role\Repository;

use Administration\Domain\Role\ValueObject\RoleId;
use Administration\Domain\Staff\ValueObject\StaffId;
use DateTimeImmutable;

interface RoleAssignmentRepository
{
    public function exists(RoleId $roleId, StaffId $staffId): bool;

    public function add(
        RoleId $roleId,
        StaffId $staffId,
        DateTimeImmutable $assignedAt
    ): void;
}
