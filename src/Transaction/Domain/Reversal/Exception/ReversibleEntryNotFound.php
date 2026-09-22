<?php

declare(strict_types=1);

namespace Transaction\Domain\Reversal\Exception;

use Shared\Domain\Exception\DomainException;

final class ReversibleEntryNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('The requested posted ledger entry cannot be reversed.');
    }
}
