<?php

declare(strict_types=1);

namespace Identity\Application\Authorization;

use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;

/**
 * Carries the user and requested permission into the authorization use case.
 */
final readonly class AuthorizeUserCommand
{
    public function __construct(
        public UserId $userId,
        public Permission $permission,
    ) {}
}
