<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Uniquely identifies a staff record. */
final class StaffId extends Uuid {}
