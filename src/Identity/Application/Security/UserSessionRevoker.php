<?php

declare(strict_types=1);

namespace Identity\Application\Security;

use Identity\Domain\User\ValueObject\UserId;

/** Removes every active API session that belongs to one identity user. */
interface UserSessionRevoker
{
    public function revokeAll(UserId $userId): void;
}
