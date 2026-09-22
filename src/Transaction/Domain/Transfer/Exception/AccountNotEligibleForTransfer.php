<?php

declare(strict_types=1);

namespace Transaction\Domain\Transfer\Exception;

use Shared\Domain\Exception\DomainException;

final class AccountNotEligibleForTransfer extends DomainException
{
    public static function create(): self
    {
        return new self('Both the sender and recipient accounts must be active to complete a transfer.');
    }
}
