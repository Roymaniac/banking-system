<?php

declare(strict_types=1);

namespace Identity\Domain\Authentication\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Uses one message for every login failure so the system never reveals whether
 * an email address exists.
 */
final class InvalidCredentials extends DomainException
{
    public static function create(): self
    {
        return new self('The supplied credentials are invalid.');
    }
}
