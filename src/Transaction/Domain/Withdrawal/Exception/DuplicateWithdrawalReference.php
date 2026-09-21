<?php

declare(strict_types=1);

namespace Transaction\Domain\Withdrawal\Exception;

use Shared\Domain\Exception\DomainException;

final class DuplicateWithdrawalReference extends DomainException
{
    public static function create(): self
    {
        return new self('A withdrawal with this reference has already been completed.');
    }
}
