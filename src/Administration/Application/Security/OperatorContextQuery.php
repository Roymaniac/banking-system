<?php

declare(strict_types=1);

namespace Administration\Application\Security;

use Identity\Domain\User\ValueObject\UserId;

/** Provides the signed-in staff member's safe navigation and permission context. */
interface OperatorContextQuery
{
    /** @return array<string, mixed>|null */
    public function forUser(UserId $userId): ?array;
}
