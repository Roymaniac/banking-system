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
use Shared\Contracts\TransactionManager;

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
        private TransactionManager $transactions,
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

        $request = new EmailVerificationRequest(
            userId: $command->userId,
            tokenHash: EmailVerificationTokenHash::fromToken($token),
            requestedAt: $requestedAt,
            expiresAt: $expiresAt,
        );

        $this->transactions->run(function () use ($request, $user, $token, $expiresAt): void {
            $this->requests->replace($request);
            // The token hash and encrypted outbox email now commit together.
            $this->notifier->send($user->email(), $token, $expiresAt);
        });
    }
}
