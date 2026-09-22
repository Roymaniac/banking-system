<?php

declare(strict_types=1);

namespace Transaction\Domain\Transfer\Exception;

use Shared\Domain\Exception\DomainException;

final class TransferLedgerUnavailable extends DomainException
{
    public static function create(): self
    {
        return new self('The sender or recipient ledger is unavailable.');
    }
}
