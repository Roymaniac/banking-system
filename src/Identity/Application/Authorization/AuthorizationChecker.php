<?php

declare(strict_types=1);

namespace Identity\Application\Authorization;

use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;

/**
 * Lets application services check permissions without depending on Laravel,
 * Spatie, database tables, or any other authorization technology.
 */
interface AuthorizationChecker
{
    public function allows(UserId $userId, Permission $permission): bool;
}
