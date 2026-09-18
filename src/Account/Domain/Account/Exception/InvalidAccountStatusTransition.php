<?php

declare(strict_types=1);

namespace Account\Domain\Account\Exception;

use Account\Domain\Account\ValueObject\AccountStatus;
use Shared\Domain\Exception\DomainException;

final class InvalidAccountStatusTransition extends DomainException
{
    public static function fromTo(AccountStatus $from, AccountStatus $to): self
    {
        return new self(sprintf(
            'An account cannot move from %s status to %s status.',
            $from->value,
            $to->value,
        ));
    }
}
