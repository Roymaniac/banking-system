<?php

declare(strict_types=1);

namespace Identity\Domain\User;

use DateTimeImmutable;
use Identity\Domain\User\Event\UserEmailVerified;
use Identity\Domain\User\Event\UserPasswordReset;
use Identity\Domain\User\Event\UserRegistered;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\PasswordHash;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/**
 * The User aggregate represents a person's sign-in identity.
 *
 * Personal profile information belongs to the Customer module. Keeping it out
 * of this aggregate prevents authentication concerns from becoming mixed with
 * customer and banking data.
 */
final class User extends AggregateRoot
{
    private function __construct(
        private readonly UserId $id,
        private EmailAddress $email,
        private PasswordHash $passwordHash,
        private readonly DateTimeImmutable $registeredAt,
        private ?DateTimeImmutable $emailVerifiedAt = null,
    ) {}

    /**
     * Creates a brand-new user and records the registration as a domain event.
     */
    public static function register(
        UserId $id,
        EmailAddress $email,
        PasswordHash $passwordHash,
        DateTimeImmutable $registeredAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): self {
        $user = new self($id, $email, $passwordHash, $registeredAt);

        $user->record(new UserRegistered(
            eventId: $eventId,
            userId: $id,
            occurredOn: $registeredAt,
            email: $email,
            correlationId: $correlationId,
        ));

        return $user;
    }

    /**
     * Rebuilds an existing user from storage without pretending it registered
     * again. Reconstitution therefore records no new domain event.
     */
    public static function reconstitute(
        UserId $id,
        EmailAddress $email,
        PasswordHash $passwordHash,
        DateTimeImmutable $registeredAt,
        int $version,
        ?DateTimeImmutable $emailVerifiedAt = null,
    ): self {
        $user = new self($id, $email, $passwordHash, $registeredAt, $emailVerifiedAt);
        $user->reconstituteAtVersion($version);

        return $user;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function passwordHash(): PasswordHash
    {
        return $this->passwordHash;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    /**
     * Marks the email as verified once. Repeated calls are harmless and do not
     * create duplicate events.
     */
    public function verifyEmail(
        DateTimeImmutable $verifiedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        if ($this->isEmailVerified()) {
            return;
        }

        $this->emailVerifiedAt = $verifiedAt;

        $this->record(new UserEmailVerified(
            eventId: $eventId,
            userId: $this->id,
            aggregateVersion: $this->version() + 1,
            occurredOn: $verifiedAt,
            correlationId: $correlationId,
        ));
    }

    /**
     * Replaces the stored password hash and records a security event.
     */
    public function resetPassword(
        PasswordHash $newPasswordHash,
        DateTimeImmutable $resetAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        $this->passwordHash = $newPasswordHash;

        $this->record(new UserPasswordReset(
            eventId: $eventId,
            userId: $this->id,
            aggregateVersion: $this->version() + 1,
            occurredOn: $resetAt,
            correlationId: $correlationId,
        ));
    }
}
