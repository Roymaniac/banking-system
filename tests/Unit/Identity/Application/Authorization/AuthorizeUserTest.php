<?php

declare(strict_types=1);

use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Application\Authorization\AuthorizeUser;
use Identity\Application\Authorization\AuthorizeUserCommand;
use Identity\Domain\Authorization\Exception\AccessDenied;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;

final class AuthorizationTestChecker implements AuthorizationChecker
{
    public int $checks = 0;

    public function __construct(
        private bool $allowed,
    ) {}

    public function allows(UserId $userId, Permission $permission): bool
    {
        $this->checks++;

        return $this->allowed;
    }
}

it('allows a user who has the required permission', function (): void {
    $checker = new AuthorizationTestChecker(true);
    $service = new AuthorizeUser($checker);

    $service->handle(new AuthorizeUserCommand(
        UserId::generate(),
        new Permission('accounts.view'),
    ));

    expect($checker->checks)->toBe(1);
});

it('rejects a user who lacks the required permission', function (): void {
    $service = new AuthorizeUser(new AuthorizationTestChecker(false));

    $service->handle(new AuthorizeUserCommand(
        UserId::generate(),
        new Permission('transfers.approve'),
    ));
})->throws(AccessDenied::class, 'You are not authorized to perform this action.');
