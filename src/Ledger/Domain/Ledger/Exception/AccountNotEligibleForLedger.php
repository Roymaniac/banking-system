<?php

declare(strict_types=1);

namespace Ledger\Domain\Ledger\Exception;

use Shared\Domain\Exception\DomainException;

final class AccountNotEligibleForLedger extends DomainException
{
    public static function create(): self
    {
        // One message avoids revealing whether an account ID exists.
        return new self('An active account is required to create a ledger.');
    }
}
