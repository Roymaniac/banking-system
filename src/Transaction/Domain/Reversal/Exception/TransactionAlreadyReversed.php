<?php

declare(strict_types=1);

namespace Transaction\Domain\Reversal\Exception;

use Shared\Domain\Exception\DomainException;

final class TransactionAlreadyReversed extends DomainException
{
    public static function create(): self
    {
        return new self('This ledger entry has already been reversed.');
    }
}
