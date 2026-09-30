<?php

declare(strict_types=1);

namespace Administration\Domain\Role\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Uniquely identifies an administrative role. */
final class RoleId extends Uuid {}
