<?php

declare(strict_types=1);

namespace Ledger\Domain\Balance\Exception;

use Shared\Domain\Exception\DomainException;

final class UnpostedEntryCannotBeProjected extends DomainException
{
    public static function create(): self
    {
        return new self('Only a posted ledger entry can affect projected balances.');
    }
}
