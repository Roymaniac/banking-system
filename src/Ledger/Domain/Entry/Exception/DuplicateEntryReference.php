<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Exception;

use Shared\Domain\Exception\DomainException;

final class DuplicateEntryReference extends DomainException
{
    public static function create(): self
    {
        return new self('This ledger already contains an entry with the same reference.');
    }
}
