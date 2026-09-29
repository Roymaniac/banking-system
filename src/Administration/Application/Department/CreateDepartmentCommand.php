<?php

declare(strict_types=1);

namespace Administration\Application\Department;

use Shared\Domain\Identifier\CorrelationId;

final readonly class CreateDepartmentCommand
{
    public function __construct(
        public string $code,
        public string $name,
        public ?CorrelationId $correlationId = null
    ) {}
}
