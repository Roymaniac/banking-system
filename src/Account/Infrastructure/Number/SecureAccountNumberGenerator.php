<?php

declare(strict_types=1);

namespace Account\Infrastructure\Number;

use Account\Application\Number\AccountNumberGenerator;
use Account\Domain\Account\ValueObject\AccountNumber;

/** Uses the operating system's secure random source for account numbers. */
final readonly class SecureAccountNumberGenerator implements AccountNumberGenerator
{
    public function generate(): AccountNumber
    {
        return new AccountNumber((string) random_int(1_000_000_000, 9_999_999_999));
    }
}
