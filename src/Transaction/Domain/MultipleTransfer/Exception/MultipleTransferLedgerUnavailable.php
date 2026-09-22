<?php

declare(strict_types=1);

namespace Transaction\Domain\MultipleTransfer\Exception;

use Shared\Domain\Exception\DomainException;

final class MultipleTransferLedgerUnavailable extends DomainException
{
    public static function create(): self
    {
        return new self('A required sender or recipient ledger is unavailable.');
    }
}
