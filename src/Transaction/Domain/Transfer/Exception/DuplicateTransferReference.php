<?php

declare(strict_types=1);

namespace Transaction\Domain\Transfer\Exception;

use Shared\Domain\Exception\DomainException;

final class DuplicateTransferReference extends DomainException
{
    public static function create(): self
    {
        return new self('A transfer with this reference has already been completed.');
    }
}
