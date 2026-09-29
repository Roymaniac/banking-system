<?php

declare(strict_types=1);

namespace Administration\Domain\Department\Repository;

use Administration\Domain\Department\Department;
use Administration\Domain\Department\ValueObject\DepartmentCode;
use Administration\Domain\Department\ValueObject\DepartmentId;

interface DepartmentRepository
{
    public function save(Department $department): void;

    public function findById(DepartmentId $id): ?Department;

    public function findByIdForUpdate(DepartmentId $id): ?Department;

    public function codeExists(DepartmentCode $code): bool;
}
