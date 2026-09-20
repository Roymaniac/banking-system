<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Exception;

use Shared\Domain\Exception\DomainException;

final class UnbalancedLedgerEntry extends DomainException
{
    public static function create(): self
    {
        return new self('Total debits must equal total credits before an entry can be posted.');
    }
}
