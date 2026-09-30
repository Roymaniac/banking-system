<?php

declare(strict_types=1);

namespace Administration\Domain\Permission\Exception;

use Shared\Domain\Exception\DomainException;

final class PermissionNotGranted extends DomainException
{
    public static function create(): self
    {
        return new self('The role does not have this permission.');
    }
}
