<?php

declare(strict_types=1);

namespace Identity\Application\Registration;

use DateInterval;
use Identity\Application\Authentication\PasswordHasher;
use Identity\Application\EmailVerification\EmailVerificationNotifier;
use Identity\Application\EmailVerification\EmailVerificationTokenGenerator;
use Identity\Domain\Authentication\ValueObject\PlainPassword;
use Identity\Domain\EmailVerification\EmailVerificationRequest;
use Identity\Domain\EmailVerification\Repository\EmailVerificationRequestRepository;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationTokenHash;
use Identity\Domain\User\Exception\EmailAlreadyRegistered;
use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\User;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Creates an unverified user and its first verification email atomically. */
final readonly class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private EmailVerificationRequestRepository $verificationRequests,
        private EmailVerificationTokenGenerator $tokenGenerator,
        private EmailVerificationNotifier $notifier,
        private Clock $clock,
        private UuidGenerator $ids,
        private TransactionManager $transactions,
        private EventPublisher $events,
        private int $verificationLifetimeMinutes = 1440,
    ) {
        if ($verificationLifetimeMinutes < 1) {
            throw new InvalidArgumentException('An email-verification lifetime must be at least one minute.');
        }
    }

    public function handle(RegisterUserCommand $command): User
    {
        $email = new EmailAddress($command->email);

        if ($this->users->emailExists($email)) {
            throw EmailAlreadyRegistered::create();
        }

        $now = $this->clock->now();
        $token = $this->tokenGenerator->generate();
        $expiresAt = $now->add(new DateInterval(sprintf('PT%dM', $this->verificationLifetimeMinutes)));
        $user = User::register(
            new UserId($this->ids->generate()->value()),
            $email,
            $this->passwordHasher->hash((new PlainPassword($command->password))->value()),
            $now,
            $this->ids->generate(),
            $command->correlationId,
        );
        $verification = new EmailVerificationRequest(
            $user->id(),
            EmailVerificationTokenHash::fromToken($token),
            $now,
            $expiresAt,
        );

        try {
            $domainEvents = $this->transactions->run(function () use (
                $user,
                $verification,
                $token,
                $expiresAt,
            ): array {
                $this->users->save($user);
                $this->verificationRequests->replace($verification);
                // The encrypted outbox message commits with the user and token.
                $this->notifier->send($user->email(), $token, $expiresAt);

                return $user->pullDomainEvents();
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw EmailAlreadyRegistered::create();
            }

            throw $exception;
        }

        $this->events->publish($domainEvents);

        return $user;
    }
}
