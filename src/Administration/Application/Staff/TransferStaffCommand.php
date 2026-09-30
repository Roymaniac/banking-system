<?php

declare(strict_types=1);

namespace Administration\Application\Staff;

use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Staff\ValueObject\StaffId;
use Shared\Domain\Identifier\CorrelationId;

final readonly class TransferStaffCommand
{
    public function __construct(
        public StaffId $staffId,
        public DepartmentId $departmentId,
        public ?CorrelationId $correlationId = null
    ) {}
}
