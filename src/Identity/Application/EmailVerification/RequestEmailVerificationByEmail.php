<?php

declare(strict_types=1);

namespace Identity\Application\EmailVerification;

use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\ValueObject\EmailAddress;

/** Resends verification without revealing whether the supplied email exists. */
final readonly class RequestEmailVerificationByEmail
{
    public function __construct(
        private UserRepository $users,
        private RequestEmailVerification $requestVerification,
    ) {}

    public function handle(string $email): void
    {
        $user = $this->users->findByEmail(new EmailAddress($email));

        if ($user !== null) {
            $this->requestVerification->handle(new RequestEmailVerificationCommand($user->id()));
        }
    }
}
