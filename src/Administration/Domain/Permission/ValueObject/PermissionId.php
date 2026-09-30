<?php

declare(strict_types=1);

namespace Administration\Domain\Permission\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Uniquely identifies a permission catalogue entry. */
final class PermissionId extends Uuid {}
