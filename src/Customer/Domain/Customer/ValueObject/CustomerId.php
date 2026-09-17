<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\ValueObject;

use Shared\Domain\Identifier\Uuid;

/**
 * Gives a customer UUID its own type so it cannot be confused with another ID.
 */
final class CustomerId extends Uuid {}
