<?php

declare(strict_types=1);

namespace Identity\Application\EmailVerification;

use Identity\Domain\EmailVerification\Exception\InvalidEmailVerificationToken;
use Identity\Domain\EmailVerification\Repository\EmailVerificationRequestRepository;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationToken;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationTokenHash;
use Identity\Domain\User\Repository\UserRepository;
use InvalidArgumentException;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/**
 * Proves email ownership and consumes the token as one atomic operation.
 */
final readonly class VerifyEmail
{
    public function __construct(
        private EmailVerificationRequestRepository $requests,
        private UserRepository $users,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(VerifyEmailCommand $command): void
    {
        try {
            $tokenHash = EmailVerificationTokenHash::fromToken(
                new EmailVerificationToken($command->token),
            );
        } catch (InvalidArgumentException) {
            throw InvalidEmailVerificationToken::create();
        }

        $request = $this->requests->findByTokenHash($tokenHash);
        $now = $this->clock->now();

        if ($request === null || $request->isExpiredAt($now)) {
            throw InvalidEmailVerificationToken::create();
        }

        $user = $this->users->findById($request->userId());

        if ($user === null) {
            throw InvalidEmailVerificationToken::create();
        }

        $domainEvents = $this->transactions->run(function () use (
            $user,
            $now,
            $tokenHash,
            $command,
        ): array {
            $user->verifyEmail(
                verifiedAt: $now,
                eventId: $this->uuidGenerator->generate(),
                correlationId: $command->correlationId,
            );

            $this->users->save($user);
            $this->requests->consume($tokenHash);

            return $user->pullDomainEvents();
        });

        // Events are dispatched only after both database writes succeed.
        $this->events->publish($domainEvents);
    }
}
