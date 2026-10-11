<?php

declare(strict_types=1);

namespace Compliance\Domain\DueDiligence\Exception;

use Shared\Domain\Exception\DomainException;

final class DuplicateIdentityDocument extends DomainException
{
    public static function create(): self
    {
        return new self('This identity document is already linked to another customer.');
    }
}
