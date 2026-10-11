<?php

declare(strict_types=1);

namespace Compliance\Domain\DueDiligence\Exception;

use Shared\Domain\Exception\DomainException;

final class InvalidDueDiligenceAction extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
