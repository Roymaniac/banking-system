<?php

declare(strict_types=1);

namespace Identity\Application\PasswordReset;

use DateInterval;
use Identity\Domain\PasswordReset\PasswordResetRequest;
use Identity\Domain\PasswordReset\Repository\PasswordResetRequestRepository;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetTokenHash;
use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\ValueObject\EmailAddress;
use InvalidArgumentException;
use Shared\Contracts\Clock;

/**
 * Creates and delivers a password-reset request when the email belongs to a
 * user. It returns no result in either case, preventing account discovery.
 */
final readonly class RequestPasswordReset
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetRequestRepository $requests,
        private PasswordResetTokenGenerator $tokenGenerator,
        private PasswordResetNotifier $notifier,
        private Clock $clock,
        private int $lifetimeMinutes = 30,
    ) {
        if ($lifetimeMinutes < 1) {
            throw new InvalidArgumentException('A password-reset lifetime must be at least one minute.');
        }
    }

    public function handle(RequestPasswordResetCommand $command): void
    {
        $email = new EmailAddress($command->email);
        $user = $this->users->findByEmail($email);

        // The public response must be identical whether or not the email exists.
        if ($user === null) {
            return;
        }

        $token = $this->tokenGenerator->generate();
        $requestedAt = $this->clock->now();
        $expiresAt = $requestedAt->add(new DateInterval(sprintf('PT%dM', $this->lifetimeMinutes)));

        $this->requests->replace(new PasswordResetRequest(
            email: $email,
            tokenHash: PasswordResetTokenHash::fromToken($token),
            requestedAt: $requestedAt,
            expiresAt: $expiresAt,
        ));

        // Only the notifier receives the raw secret. Storage receives its hash.
        $this->notifier->send($email, $token, $expiresAt);
    }
}
