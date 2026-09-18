<?php

declare(strict_types=1);

namespace Account\Application\Number;

use Account\Domain\Account\ValueObject\AccountNumber;

/** Generates candidate account numbers without knowing about storage. */
interface AccountNumberGenerator
{
    public function generate(): AccountNumber;
}
