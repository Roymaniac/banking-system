<?php

declare(strict_types=1);

namespace Ledger\Domain\Ledger\ValueObject;

use Shared\Domain\Identifier\Uuid;

/** Gives each ledger a permanent identity independent of its account. */
final class LedgerId extends Uuid {}
