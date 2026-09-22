<?php

declare(strict_types=1);

namespace Transaction\Domain\Transfer\Exception;

use Shared\Domain\Exception\DomainException;

final class InsufficientTransferFunds extends DomainException
{
    public static function create(): self
    {
        return new self('The sender account does not have enough available funds for this transfer.');
    }
}
