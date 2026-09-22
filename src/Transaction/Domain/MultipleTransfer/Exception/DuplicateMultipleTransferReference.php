<?php

declare(strict_types=1);

namespace Transaction\Domain\MultipleTransfer\Exception;

use Shared\Domain\Exception\DomainException;

final class DuplicateMultipleTransferReference extends DomainException
{
    public static function create(): self
    {
        return new self('A multiple transfer with this reference has already been completed.');
    }
}
