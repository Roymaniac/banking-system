<?php

declare(strict_types=1);

namespace Administration\Application\Department;

use Administration\Domain\Department\ValueObject\DepartmentId;
use Shared\Domain\Identifier\CorrelationId;

final readonly class RenameDepartmentCommand
{
    public function __construct(
        public DepartmentId $departmentId,
        public string $name,
        public ?CorrelationId $correlationId = null
    ) {}
}
