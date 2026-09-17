<?php

declare(strict_types=1);

use Identity\Application\Authentication\PasswordHasher;
use Identity\Application\PasswordReset\ResetPassword;
use Identity\Application\PasswordReset\ResetPasswordCommand;
use Identity\Domain\PasswordReset\Exception\InvalidPasswordResetToken;
use Identity\Domain\PasswordReset\PasswordResetRequest;
use Identity\Domain\PasswordReset\Repository\PasswordResetRequestRepository;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetTokenHash;
use Identity\Domain\User\Event\UserPasswordReset;
use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\User;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\PasswordHash;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

final readonly class ResetPasswordTestClock implements Clock
{
    public function __construct(private DateTimeImmutable $time) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

final class ResetPasswordTestTransactionManager implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class ResetPasswordTestEventPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function resetPasswordTestUser(): User
{
    return User::reconstitute(
        UserId::generate(),
        new EmailAddress('member@example.com'),
        new PasswordHash(password_hash('old-password-value', PASSWORD_BCRYPT)),
        new DateTimeImmutable('2026-09-01T08:00:00+00:00'),
        1,
    );
}

it('changes the password, consumes the token, and publishes the event', function (): void {
    $rawToken = new PasswordResetToken(str_repeat('e', 64));
    $tokenHash = PasswordResetTokenHash::fromToken($rawToken);
    $request = new PasswordResetRequest(
        new EmailAddress('member@example.com'),
        $tokenHash,
        new DateTimeImmutable('2026-09-16T10:00:00+00:00'),
        new DateTimeImmutable('2026-09-16T10:30:00+00:00'),
    );
    $user = resetPasswordTestUser();
    $newHash = new PasswordHash(password_hash('new-password-value', PASSWORD_BCRYPT));

    $requests = Mockery::mock(PasswordResetRequestRepository::class);
    $requests->shouldReceive('findByTokenHash')->once()->with(Mockery::on(
        fn (PasswordResetTokenHash $hash): bool => $hash->equals($tokenHash),
    ))->andReturn($request);
    $requests->shouldReceive('consume')->once()->with(Mockery::on(
        fn (PasswordResetTokenHash $hash): bool => $hash->equals($tokenHash),
    ));

    $users = Mockery::mock(UserRepository::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);
    $users->shouldReceive('save')->once()->with($user);

    $passwordHasher = Mockery::mock(PasswordHasher::class);
    $passwordHasher->shouldReceive('hash')->once()->with('a-new-secure-password')->andReturn($newHash);

    $uuidGenerator = Mockery::mock(UuidGenerator::class);
    $uuidGenerator->shouldReceive('generate')->once()->andReturn(Uuid::generate());

    $publisher = new ResetPasswordTestEventPublisher;
    $service = new ResetPassword(
        $requests,
        $users,
        $passwordHasher,
        new ResetPasswordTestClock(new DateTimeImmutable('2026-09-16T10:15:00+00:00')),
        $uuidGenerator,
        new ResetPasswordTestTransactionManager,
        $publisher,
    );

    $service->handle(new ResetPasswordCommand($rawToken->value(), 'a-new-secure-password'));

    expect($user->passwordHash())->toBe($newHash)
        ->and($user->version())->toBe(2)
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(UserPasswordReset::class)
        ->and($publisher->published[0]->payload())->toBeEmpty();
});

it('rejects an expired reset token', function (): void {
    $rawToken = new PasswordResetToken(str_repeat('f', 64));
    $request = new PasswordResetRequest(
        new EmailAddress('member@example.com'),
        PasswordResetTokenHash::fromToken($rawToken),
        new DateTimeImmutable('2026-09-16T10:00:00+00:00'),
        new DateTimeImmutable('2026-09-16T10:30:00+00:00'),
    );

    $requests = Mockery::mock(PasswordResetRequestRepository::class);
    $requests->shouldReceive('findByTokenHash')->once()->andReturn($request);

    $service = new ResetPassword(
        $requests,
        Mockery::mock(UserRepository::class),
        Mockery::mock(PasswordHasher::class),
        new ResetPasswordTestClock(new DateTimeImmutable('2026-09-16T10:30:00+00:00')),
        Mockery::mock(UuidGenerator::class),
        new ResetPasswordTestTransactionManager,
        new ResetPasswordTestEventPublisher,
    );

    $service->handle(new ResetPasswordCommand($rawToken->value(), 'a-new-secure-password'));
})->throws(InvalidPasswordResetToken::class, 'The password-reset token is invalid or has expired.');

it('hides the reason a malformed reset token is rejected', function (): void {
    $service = new ResetPassword(
        Mockery::mock(PasswordResetRequestRepository::class),
        Mockery::mock(UserRepository::class),
        Mockery::mock(PasswordHasher::class),
        new ResetPasswordTestClock(new DateTimeImmutable('2026-09-16T10:15:00+00:00')),
        Mockery::mock(UuidGenerator::class),
        new ResetPasswordTestTransactionManager,
        new ResetPasswordTestEventPublisher,
    );

    $service->handle(new ResetPasswordCommand('not-a-valid-token', 'a-new-secure-password'));
})->throws(InvalidPasswordResetToken::class, 'The password-reset token is invalid or has expired.');
