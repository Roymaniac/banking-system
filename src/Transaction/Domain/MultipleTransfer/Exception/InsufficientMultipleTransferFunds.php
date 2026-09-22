<?php

declare(strict_types=1);

namespace Transaction\Domain\MultipleTransfer\Exception;

use Shared\Domain\Exception\DomainException;

final class InsufficientMultipleTransferFunds extends DomainException
{
    public static function create(): self
    {
        return new self('The sender account does not have enough available funds for all recipients.');
    }
}
