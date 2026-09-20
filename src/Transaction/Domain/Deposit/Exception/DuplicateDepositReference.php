<?php

declare(strict_types=1);

namespace Transaction\Domain\Deposit\Exception;

use Shared\Domain\Exception\DomainException;

final class DuplicateDepositReference extends DomainException
{
    public static function create(): self
    {
        return new self('A deposit with this reference has already been completed.');
    }
}
