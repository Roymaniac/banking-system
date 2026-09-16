<?php

declare(strict_types=1);

use Identity\Application\Authentication\AuthenticateUser;
use Identity\Application\Authentication\AuthenticateUserCommand;
use Identity\Application\Authentication\PasswordHasher;
use Identity\Domain\Authentication\Exception\InvalidCredentials;
use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\User;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\PasswordHash;
use Identity\Domain\User\ValueObject\UserId;

final class AuthenticationTestUserRepository implements UserRepository
{
    public function __construct(
        private readonly ?User $user,
    ) {}

    public function save(User $user): void {}

    public function findById(UserId $id): ?User
    {
        return $this->user?->id()->equals($id) === true ? $this->user : null;
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        return $this->user?->email()->equals($email) === true ? $this->user : null;
    }

    public function emailExists(EmailAddress $email): bool
    {
        return $this->findByEmail($email) !== null;
    }
}

final class AuthenticationTestPasswordHasher implements PasswordHasher
{
    public int $verificationAttempts = 0;

    public function hash(string $plainPassword): PasswordHash
    {
        return new PasswordHash(password_hash($plainPassword, PASSWORD_BCRYPT));
    }

    public function verify(string $plainPassword, ?PasswordHash $passwordHash): bool
    {
        $this->verificationAttempts++;

        return $passwordHash !== null
            && password_verify($plainPassword, $passwordHash->value());
    }

    public function needsRehash(PasswordHash $passwordHash): bool
    {
        return false;
    }
}

function authenticationTestUser(): User
{
    return User::reconstitute(
        UserId::generate(),
        new EmailAddress('member@example.com'),
        new PasswordHash(password_hash('correct-password', PASSWORD_BCRYPT)),
        new DateTimeImmutable('2026-09-16T10:00:00+00:00'),
        1,
    );
}

it('returns the user when the credentials are correct', function (): void {
    $user = authenticationTestUser();
    $service = new AuthenticateUser(
        new AuthenticationTestUserRepository($user),
        new AuthenticationTestPasswordHasher,
    );

    $authenticatedUser = $service->handle(
        new AuthenticateUserCommand('MEMBER@example.com', 'correct-password'),
    );

    expect($authenticatedUser)->toBe($user);
});

it('rejects an incorrect password with a generic error', function (): void {
    $service = new AuthenticateUser(
        new AuthenticationTestUserRepository(authenticationTestUser()),
        new AuthenticationTestPasswordHasher,
    );

    $service->handle(new AuthenticateUserCommand('member@example.com', 'wrong-password'));
})->throws(InvalidCredentials::class, 'The supplied credentials are invalid.');

it('still verifies a password when the email is unknown', function (): void {
    $passwordHasher = new AuthenticationTestPasswordHasher;
    $service = new AuthenticateUser(
        new AuthenticationTestUserRepository(null),
        $passwordHasher,
    );

    expect(fn() => $service->handle(
        new AuthenticateUserCommand('missing@example.com', 'any-password'),
    ))->toThrow(InvalidCredentials::class, 'The supplied credentials are invalid.');

    expect($passwordHasher->verificationAttempts)->toBe(1);
});
