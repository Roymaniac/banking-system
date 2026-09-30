<?php

declare(strict_types=1);

namespace Administration\Domain\Permission\Exception;

use Shared\Domain\Exception\DomainException;

final class PermissionAlreadyGranted extends DomainException
{
    public static function create(): self
    {
        return new self('The role already has this permission.');
    }
}
