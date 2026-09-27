<?php

declare(strict_types=1);

namespace Identity\Application\Security;

use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;

/** Reports security-sensitive Identity outcomes without knowing where they are stored. */
interface SecurityMonitor
{
    public function loginSucceeded(UserId $userId): void;

    public function loginFailed(string $email): void;

    public function unverifiedLoginBlocked(UserId $userId): void;

    public function accessDenied(UserId $userId, Permission $permission): void;
}
