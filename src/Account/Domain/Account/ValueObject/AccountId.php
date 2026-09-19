<?php

declare(strict_types=1);

namespace Account\Domain\Account\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Gives every bank account a permanent internal identity. */
final class AccountId extends Uuid {}
