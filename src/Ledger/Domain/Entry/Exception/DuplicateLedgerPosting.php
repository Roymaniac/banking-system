<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Exception;

use Shared\Domain\Exception\DomainException;

final class DuplicateLedgerPosting extends DomainException
{
    public static function create(): self
    {
        return new self('A ledger entry may contain only one posting for each ledger.');
    }
}
