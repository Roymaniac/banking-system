<?php

declare(strict_types=1);

namespace Transaction\Domain\Withdrawal\Exception;

use Shared\Domain\Exception\DomainException;

final class AccountNotEligibleForWithdrawal extends DomainException
{
    public static function create(): self
    {
        return new self('An active account is required to complete a withdrawal.');
    }
}
