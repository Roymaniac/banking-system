<?php

declare(strict_types=1);

namespace Transaction\Application\Control\Exception;

use Shared\Domain\Exception\DomainException;

final class MoneyMovementSuspended extends DomainException
{
    public static function create(): self
    {
        return new self('Money movement is temporarily suspended.');
    }
}
