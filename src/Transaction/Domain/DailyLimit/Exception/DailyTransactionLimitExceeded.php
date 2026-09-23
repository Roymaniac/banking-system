<?php

declare(strict_types=1);

namespace Transaction\Domain\DailyLimit\Exception;

use Shared\Domain\Exception\DomainException;

final class DailyTransactionLimitExceeded extends DomainException
{
    public static function create(): self
    {
        return new self('This transaction would exceed the account daily outgoing limit.');
    }
}
