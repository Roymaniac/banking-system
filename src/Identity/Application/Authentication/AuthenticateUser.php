<?php

declare(strict_types=1);

namespace Identity\Application\Authentication;

use Identity\Domain\Authentication\Exception\EmailNotVerified;
use Identity\Domain\Authentication\Exception\InvalidCredentials;
use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\User;
use Identity\Domain\User\ValueObject\EmailAddress;

/**
 * Verifies login credentials without knowing how users or hashes are stored.
 */
final readonly class AuthenticateUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
    ) {}

    public function handle(AuthenticateUserCommand $command): User
    {
        $user = $this->users->findByEmail(new EmailAddress($command->email));

        // Verification is attempted even when the user is missing. This makes
        // “unknown email” and “wrong password” take similar amounts of time.
        $passwordMatches = $this->passwordHasher->verify(
            $command->password,
            $user?->passwordHash(),
        );

        if ($user === null || ! $passwordMatches) {
            throw InvalidCredentials::create();
        }

        if (! $user->isEmailVerified()) {
            throw EmailNotVerified::create();
        }

        return $user;
    }
}
