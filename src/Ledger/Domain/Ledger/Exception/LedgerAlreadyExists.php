<?php

declare(strict_types=1);

namespace Ledger\Domain\Ledger\Exception;

use Shared\Domain\Exception\DomainException;

final class LedgerAlreadyExists extends DomainException
{
    public static function create(): self
    {
        return new self('A ledger already exists for this account.');
    }
}
