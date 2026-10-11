<?php

declare(strict_types=1);

namespace Identity\Application\Session;

use Identity\Application\Session\View\UserSessionPage;
use Identity\Domain\User\ValueObject\UserId;

/** Reads only sessions owned by the authenticated identity. */
interface UserSessionQuery
{
    public function paginate(
        UserId $userId,
        int $currentSessionId,
        int $page,
        int $perPage,
    ): UserSessionPage;
}
