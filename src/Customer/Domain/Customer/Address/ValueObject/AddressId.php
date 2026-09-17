<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Address\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Identifies one address belonging to a customer. */
final class AddressId extends Uuid {}
