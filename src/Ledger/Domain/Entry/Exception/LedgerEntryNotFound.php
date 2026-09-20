<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Exception;

use Shared\Domain\Exception\DomainException;

final class LedgerEntryNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('The requested ledger entry does not exist.');
    }
}
