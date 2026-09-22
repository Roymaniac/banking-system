<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Exception;

use Shared\Domain\Exception\DomainException;

final class InsufficientPostings extends DomainException
{
    public static function create(): self
    {
        return new self('A ledger entry needs at least one debit and one credit posting.');
    }
}
