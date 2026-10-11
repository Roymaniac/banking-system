<?php

declare(strict_types=1);

namespace Identity\Application\Security;

use Identity\Domain\User\ValueObject\UserId;

/** Removes every active API session that belongs to one identity user. */
interface UserSessionRevoker
{
    public function revokeAll(UserId $userId): void;

    /** Returns false when the requested session is not owned by this user. */
    public function revoke(UserId $userId, int $sessionId): bool;

    /** Revokes every API session except the one completing a safe action. */
    public function revokeAllExcept(UserId $userId, ?int $keptSessionId): void;
}
