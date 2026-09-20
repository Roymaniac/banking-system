<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Exception;

use Shared\Domain\Exception\DomainException;

final class PostingCurrencyMismatch extends DomainException
{
    public static function create(): self
    {
        return new self('Every posting in a ledger entry must use the same currency.');
    }
}
