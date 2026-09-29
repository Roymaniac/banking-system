<?php

declare(strict_types=1);

namespace Administration\Application\Department;

use Administration\Domain\Department\ValueObject\DepartmentId;
use Shared\Domain\Identifier\CorrelationId;

final readonly class DeactivateDepartmentCommand
{
    public function __construct(
        public DepartmentId $departmentId,
        public ?CorrelationId $correlationId = null
    ) {}
}
