<?php

declare(strict_types=1);

namespace Identity\Domain\Authentication\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Indicates that valid credentials belong to an email awaiting verification.
 */
final class EmailNotVerified extends DomainException
{
    public static function create(): self
    {
        return new self('The email address must be verified before signing in.');
    }
}
