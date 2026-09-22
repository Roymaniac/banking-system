<?php

declare(strict_types=1);

namespace Transaction\Domain\Reversal\Exception;

use Shared\Domain\Exception\DomainException;

final class InsufficientReversalFunds extends DomainException
{
    public static function create(): self
    {
        return new self('A credited ledger no longer has enough funds to reverse this transaction safely.');
    }
}
