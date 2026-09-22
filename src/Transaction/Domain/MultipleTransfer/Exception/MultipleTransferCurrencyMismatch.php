<?php

declare(strict_types=1);

namespace Transaction\Domain\MultipleTransfer\Exception;

use Shared\Domain\Exception\DomainException;

final class MultipleTransferCurrencyMismatch extends DomainException
{
    public static function create(): self
    {
        return new self('The sender and every recipient must use the same currency.');
    }
}
