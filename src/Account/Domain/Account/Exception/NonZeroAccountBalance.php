<?php

declare(strict_types=1);

namespace Account\Domain\Account\Exception;

use Shared\Domain\Exception\DomainException;

final class NonZeroAccountBalance extends DomainException
{
    public static function create(): self
    {
        return new self('An account must have a zero balance before it can be closed.');
    }
}
