<?php

declare(strict_types=1);

namespace Identity\Application\EmailVerification;

use DateInterval;
use Identity\Domain\EmailVerification\EmailVerificationRequest;
use Identity\Domain\EmailVerification\Repository\EmailVerificationRequestRepository;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationTokenHash;
use Identity\Domain\User\Repository\UserRepository;
use InvalidArgumentException;
use Shared\Contracts\Clock;

/**
 * Creates a fresh verification link for an existing, unverified user.
 */
final readonly class RequestEmailVerification
{
    public function __construct(
        private UserRepository $users,
        private EmailVerificationRequestRepository $requests,
        private EmailVerificationTokenGenerator $tokenGenerator,
        private EmailVerificationNotifier $notifier,
        private Clock $clock,
        private int $lifetimeMinutes = 1440,
    ) {
        if ($lifetimeMinutes < 1) {
            throw new InvalidArgumentException('An email-verification lifetime must be at least one minute.');
        }
    }

    public function handle(RequestEmailVerificationCommand $command): void
    {
        $user = $this->users->findById($command->userId);

        // Already verified or unknown users do not receive another token.
        if ($user === null || $user->isEmailVerified()) {
            return;
        }

        $token = $this->tokenGenerator->generate();
        $requestedAt = $this->clock->now();
        $expiresAt = $requestedAt->add(new DateInterval(sprintf('PT%dM', $this->lifetimeMinutes)));

        $this->requests->replace(new EmailVerificationRequest(
            userId: $command->userId,
            tokenHash: EmailVerificationTokenHash::fromToken($token),
            requestedAt: $requestedAt,
            expiresAt: $expiresAt,
        ));

        // Only the notifier sees the raw token; storage receives its hash.
        $this->notifier->send($user->email(), $token, $expiresAt);
    }
}
