<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Exception;

use Shared\Domain\Exception\DomainException;

final class LedgerNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('The requested ledger does not exist.');
    }
}
