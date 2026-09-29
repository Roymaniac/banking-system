<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\Repository;

use Administration\Domain\Staff\Staff;
use Administration\Domain\Staff\ValueObject\EmployeeNumber;
use Administration\Domain\Staff\ValueObject\StaffId;
use Identity\Domain\User\ValueObject\UserId;

interface StaffRepository
{
    public function save(Staff $staff): void;

    public function findByIdForUpdate(StaffId $id): ?Staff;

    public function employeeNumberExists(EmployeeNumber $number): bool;

    public function userExists(UserId $userId): bool;
}
