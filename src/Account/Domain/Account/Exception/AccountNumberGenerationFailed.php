<?php

declare(strict_types=1);

namespace Account\Domain\Account\Exception;

use RuntimeException;

final class AccountNumberGenerationFailed extends RuntimeException
{
    public static function create(): self
    {
        return new self('A unique account number could not be generated.');
    }
}
