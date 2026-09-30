<?php

declare(strict_types=1);

namespace Administration\Application\Staff;

use Administration\Domain\Department\ValueObject\DepartmentId;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Identifier\CorrelationId;

final readonly class HireStaffCommand
{
    public function __construct(
        public UserId $userId,
        public string $employeeNumber,
        public DepartmentId $departmentId,
        public string $jobTitle,
        public ?CorrelationId $correlationId = null
    ) {}
}
