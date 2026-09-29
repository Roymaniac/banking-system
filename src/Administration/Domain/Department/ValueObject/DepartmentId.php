<?php

declare(strict_types=1);

namespace Administration\Domain\Department\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Gives a department UUID a type that cannot be confused with other IDs. */
final class DepartmentId extends Uuid {}
