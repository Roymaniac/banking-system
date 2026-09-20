<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Exception;

use Shared\Domain\Exception\DomainException;

final class PostedEntryCannotBeChanged extends DomainException
{
    public static function create(): self
    {
        return new self('A posted ledger entry is permanent and cannot be changed.');
    }
}
