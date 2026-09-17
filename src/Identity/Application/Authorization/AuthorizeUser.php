<?php

declare(strict_types=1);

namespace Identity\Application\Authorization;

use Identity\Domain\Authorization\Exception\AccessDenied;

/**
 * Stops a use case unless the user has its required permission.
 */
final readonly class AuthorizeUser
{
    public function __construct(
        private AuthorizationChecker $authorizationChecker,
    ) {}

    public function handle(AuthorizeUserCommand $command): void
    {
        if (! $this->authorizationChecker->allows($command->userId, $command->permission)) {
            throw AccessDenied::create();
        }
    }
}
