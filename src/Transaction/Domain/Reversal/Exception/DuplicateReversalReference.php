<?php

declare(strict_types=1);

namespace Transaction\Domain\Reversal\Exception;

use Shared\Domain\Exception\DomainException;

final class DuplicateReversalReference extends DomainException
{
    public static function create(): self
    {
        return new self('A reversal with this reference has already been completed.');
    }
}
