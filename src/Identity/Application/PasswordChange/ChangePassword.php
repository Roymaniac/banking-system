<?php

declare(strict_types=1);

namespace Identity\Application\PasswordChange;

use Identity\Application\Authentication\PasswordHasher;
use Identity\Application\Security\UserSessionRevoker;
use Identity\Domain\Authentication\Exception\InvalidCredentials;
use Identity\Domain\Authentication\ValueObject\PlainPassword;
use Identity\Domain\User\Repository\UserRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Changes a signed-in user's password after checking the current password. */
final readonly class ChangePassword
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private UserSessionRevoker $sessions,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(ChangePasswordCommand $command): void
    {
        $user = $this->users->findById($command->userId);

        if ($user === null || ! $this->passwordHasher->verify(
            $command->currentPassword,
            $user->passwordHash(),
        )) {
            throw InvalidCredentials::create();
        }

        $newPassword = new PlainPassword($command->newPassword);
        $newPasswordHash = $this->passwordHasher->hash($newPassword->value());
        $changedAt = $this->clock->now();

        // Saving the password and removing old sessions together means a
        // partial database failure cannot leave the account half-secured.
        $domainEvents = $this->transactions->run(function () use (
            $user,
            $newPasswordHash,
            $changedAt,
            $command,
        ): array {
            $user->changePassword(
                $newPasswordHash,
                $changedAt,
                $this->uuidGenerator->generate(),
                $command->correlationId,
            );

            $this->users->save($user);
            $this->sessions->revokeAllExcept($user->id(), $command->currentSessionId);

            return $user->pullDomainEvents();
        });

        $this->events->publish($domainEvents);
    }
}
