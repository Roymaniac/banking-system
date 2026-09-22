<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Exception;

use Shared\Domain\Exception\DomainException;

final class OriginatingLedgerPostingRequired extends DomainException
{
    public static function create(): self
    {
        return new self('The originating ledger must be included in the entry postings.');
    }
}
