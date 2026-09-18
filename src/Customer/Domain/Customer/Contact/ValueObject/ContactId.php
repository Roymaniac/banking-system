<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Contact\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Identifies one contact record belonging to a customer. */
final class ContactId extends Uuid {}
