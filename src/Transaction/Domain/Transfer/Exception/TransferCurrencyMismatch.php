<?php

declare(strict_types=1);

namespace Transaction\Domain\Transfer\Exception;

use Shared\Domain\Exception\DomainException;

final class TransferCurrencyMismatch extends DomainException
{
    public static function create(): self
    {
        return new self('The sender and recipient accounts must use the same currency.');
    }
}
