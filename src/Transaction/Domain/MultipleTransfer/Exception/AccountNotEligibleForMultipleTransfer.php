<?php

declare(strict_types=1);

namespace Transaction\Domain\MultipleTransfer\Exception;

use Shared\Domain\Exception\DomainException;

final class AccountNotEligibleForMultipleTransfer extends DomainException
{
    public static function create(): self
    {
        return new self('The sender and every recipient account must be active.');
    }
}
