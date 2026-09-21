<?php

declare(strict_types=1);

namespace Transaction\Domain\Withdrawal\Exception;

use Shared\Domain\Exception\DomainException;

final class InsufficientFunds extends DomainException
{
    public static function create(): self
    {
        return new self('The account does not have enough available funds for this withdrawal.');
    }
}
