<?php

declare(strict_types=1);

namespace Ledger\Domain\Balance\Exception;

use RuntimeException;

final class PostedEntryMissing extends RuntimeException
{
    public static function create(): self
    {
        return new self('The posted ledger entry could not be loaded for balance projection.');
    }
}
