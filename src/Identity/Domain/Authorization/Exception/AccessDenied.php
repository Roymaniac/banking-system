<?php

declare(strict_types=1);

namespace Identity\Domain\Authorization\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Uses a generic message so rejected requests do not reveal internal roles or
 * permission assignments.
 */
final class AccessDenied extends DomainException
{
    public static function create(): self
    {
        return new self('You are not authorized to perform this action.');
    }
}
