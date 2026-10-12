<?php

declare(strict_types=1);

namespace Identity\Application\PasswordReset;

use Identity\Application\Authentication\PasswordHasher;
use Identity\Application\Security\SecurityAlert;
use Identity\Application\Security\SecurityAlertNotifier;
use Identity\Application\Security\UserSessionRevoker;
use Identity\Domain\Authentication\ValueObject\PlainPassword;
use Identity\Domain\PasswordReset\Exception\InvalidPasswordResetToken;
use Identity\Domain\PasswordReset\Repository\PasswordResetRequestRepository;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetTokenHash;
use Identity\Domain\User\Repository\UserRepository;
use InvalidArgumentException;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/**
 * Validates a reset token and changes the password as one atomic operation.
 */
final readonly class ResetPassword
{
    public function __construct(
        private PasswordResetRequestRepository $requests,
        private UserRepository $users,
        private UserSessionRevoker $sessions,
        private SecurityAlertNotifier $alerts,
        private PasswordHasher $passwordHasher,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(ResetPasswordCommand $command): void
    {
        try {
            $tokenHash = PasswordResetTokenHash::fromToken(new PasswordResetToken($command->token));
        } catch (InvalidArgumentException) {
            // Token format details are intentionally hidden from the caller.
            throw InvalidPasswordResetToken::create();
        }

        $request = $this->requests->findByTokenHash($tokenHash);
        $now = $this->clock->now();

        if ($request === null || $request->isExpiredAt($now)) {
            throw InvalidPasswordResetToken::create();
        }

        $user = $this->users->findByEmail($request->email());

        if ($user === null) {
            throw InvalidPasswordResetToken::create();
        }

        $plainPassword = new PlainPassword($command->newPassword);
        $newPasswordHash = $this->passwordHasher->hash($plainPassword->value());

        // The password, active sessions, and one-time token change together.
        // If one write fails, the database rolls all three changes back.
        $domainEvents = $this->transactions->run(function () use (
            $user,
            $newPasswordHash,
            $now,
            $tokenHash,
            $command,
        ): array {
            $user->resetPassword(
                newPasswordHash: $newPasswordHash,
                resetAt: $now,
                eventId: $this->uuidGenerator->generate(),
                correlationId: $command->correlationId,
            );

            $this->users->save($user);
            $this->sessions->revokeAll($user->id());
            $this->requests->consume($tokenHash);
            $this->alerts->send($user->email(), SecurityAlert::PasswordReset, $now);

            return $user->pullDomainEvents();
        });

        // Listeners run only after the database transaction has succeeded.
        $this->events->publish($domainEvents);
    }
}
