<?php

declare(strict_types=1);

use Identity\Domain\User\Event\UserRegistered;
use Identity\Domain\User\User;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\PasswordHash;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

it('registers a user and records a safe domain event', function (): void {
    $id = UserId::generate();
    $email = new EmailAddress('user@example.com');
    $passwordHash = new PasswordHash(password_hash('secret-password', PASSWORD_BCRYPT));
    $registeredAt = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
    $eventId = Uuid::generate();
    $correlationId = CorrelationId::generate();

    $user = User::register($id, $email, $passwordHash, $registeredAt, $eventId, $correlationId);
    $events = $user->pullDomainEvents();

    expect($user->id())->toBe($id)
        ->and($user->email())->toBe($email)
        ->and($user->passwordHash())->toBe($passwordHash)
        ->and($user->registeredAt())->toBe($registeredAt)
        ->and($user->version())->toBe(1)
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(UserRegistered::class)
        ->and($events[0]->eventId())->toBe($eventId)
        ->and($events[0]->correlationId())->toBe($correlationId)
        ->and($events[0]->payload())->toBe(['email' => 'user@example.com'])
        ->and($events[0]->payload())->not->toHaveKey('password');
});

it('reconstitutes a stored user without recording a new event', function (): void {
    $user = User::reconstitute(
        UserId::generate(),
        new EmailAddress('stored@example.com'),
        new PasswordHash(password_hash('secret-password', PASSWORD_BCRYPT)),
        new DateTimeImmutable('2026-09-16T10:00:00+00:00'),
        4,
    );

    expect($user->version())->toBe(4)
        ->and($user->recordedEvents())->toBeEmpty();
});
