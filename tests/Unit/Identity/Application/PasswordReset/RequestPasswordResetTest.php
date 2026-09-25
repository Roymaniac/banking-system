<?php

declare(strict_types=1);

use Identity\Application\PasswordReset\PasswordResetNotifier;
use Identity\Application\PasswordReset\PasswordResetTokenGenerator;
use Identity\Application\PasswordReset\RequestPasswordReset;
use Identity\Application\PasswordReset\RequestPasswordResetCommand;
use Identity\Domain\PasswordReset\PasswordResetRequest;
use Identity\Domain\PasswordReset\Repository\PasswordResetRequestRepository;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetTokenHash;
use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\User;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\PasswordHash;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Contracts\Clock;
use Shared\Contracts\TransactionManager;

final class PasswordResetTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final readonly class PasswordResetTestUserRepository implements UserRepository
{
    public function __construct(private ?User $user) {}

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

final class PasswordResetTestRequestRepository implements PasswordResetRequestRepository
{
    public ?PasswordResetRequest $stored = null;

    public function replace(PasswordResetRequest $request): void
    {
        $this->stored = $request;
    }

    public function findByTokenHash(PasswordResetTokenHash $tokenHash): ?PasswordResetRequest
    {
        return $this->stored?->tokenHash()->equals($tokenHash) === true ? $this->stored : null;
    }

    public function consume(PasswordResetTokenHash $tokenHash): void
    {
        if ($this->stored?->tokenHash()->equals($tokenHash) === true) {
            $this->stored = null;
        }
    }
}

final readonly class PasswordResetTestTokenGenerator implements PasswordResetTokenGenerator
{
    public function __construct(private PasswordResetToken $token) {}

    public function generate(): PasswordResetToken
    {
        return $this->token;
    }
}

final class PasswordResetTestNotifier implements PasswordResetNotifier
{
    public ?EmailAddress $email = null;

    public ?PasswordResetToken $token = null;

    public ?DateTimeImmutable $expiresAt = null;

    public function send(
        EmailAddress $email,
        PasswordResetToken $token,
        DateTimeImmutable $expiresAt,
    ): void {
        $this->email = $email;
        $this->token = $token;
        $this->expiresAt = $expiresAt;
    }
}

final readonly class PasswordResetTestClock implements Clock
{
    public function __construct(private DateTimeImmutable $time) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

function passwordResetTestUser(): User
{
    return User::reconstitute(
        UserId::generate(),
        new EmailAddress('member@example.com'),
        new PasswordHash(password_hash('existing-password', PASSWORD_BCRYPT)),
        new DateTimeImmutable('2026-09-01T08:00:00+00:00'),
        1,
    );
}

it('stores a token hash and sends the raw token to a known user', function (): void {
    $rawToken = new PasswordResetToken(str_repeat('c', 64));
    $requests = new PasswordResetTestRequestRepository;
    $notifier = new PasswordResetTestNotifier;

    $service = new RequestPasswordReset(
        new PasswordResetTestUserRepository(passwordResetTestUser()),
        $requests,
        new PasswordResetTestTokenGenerator($rawToken),
        $notifier,
        new PasswordResetTestClock(new DateTimeImmutable('2026-09-16T10:00:00+00:00')),
        new PasswordResetTestTransactions,
    );

    $service->handle(new RequestPasswordResetCommand('MEMBER@example.com'));

    expect($requests->stored)->not->toBeNull()
        ->and($requests->stored?->tokenHash()->value())->toBe(hash('sha256', $rawToken->value()))
        ->and($notifier->email?->value())->toBe('member@example.com')
        ->and($notifier->token)->toBe($rawToken)
        ->and($notifier->expiresAt)->toEqual(new DateTimeImmutable('2026-09-16T10:30:00+00:00'));
});

it('silently ignores an unknown email address', function (): void {
    $requests = new PasswordResetTestRequestRepository;
    $notifier = new PasswordResetTestNotifier;

    $service = new RequestPasswordReset(
        new PasswordResetTestUserRepository(null),
        $requests,
        new PasswordResetTestTokenGenerator(new PasswordResetToken(str_repeat('d', 64))),
        $notifier,
        new PasswordResetTestClock(new DateTimeImmutable('2026-09-16T10:00:00+00:00')),
        new PasswordResetTestTransactions,
    );

    $service->handle(new RequestPasswordResetCommand('missing@example.com'));

    expect($requests->stored)->toBeNull()
        ->and($notifier->token)->toBeNull();
});
