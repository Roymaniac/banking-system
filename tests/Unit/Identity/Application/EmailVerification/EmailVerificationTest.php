<?php

declare(strict_types=1);

use Identity\Application\EmailVerification\EmailVerificationNotifier;
use Identity\Application\EmailVerification\EmailVerificationTokenGenerator;
use Identity\Application\EmailVerification\RequestEmailVerification;
use Identity\Application\EmailVerification\RequestEmailVerificationCommand;
use Identity\Application\EmailVerification\VerifyEmail;
use Identity\Application\EmailVerification\VerifyEmailCommand;
use Identity\Domain\EmailVerification\EmailVerificationRequest;
use Identity\Domain\EmailVerification\Exception\InvalidEmailVerificationToken;
use Identity\Domain\EmailVerification\Repository\EmailVerificationRequestRepository;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationToken;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationTokenHash;
use Identity\Domain\User\Event\UserEmailVerified;
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

final readonly class EmailVerificationTestClock implements Clock
{
    public function __construct(private DateTimeImmutable $time) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

final readonly class EmailVerificationTestTokenGenerator implements EmailVerificationTokenGenerator
{
    public function __construct(private EmailVerificationToken $token) {}

    public function generate(): EmailVerificationToken
    {
        return $this->token;
    }
}

final class EmailVerificationTestTransactionManager implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class EmailVerificationTestEventPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function emailVerificationTestUser(?DateTimeImmutable $verifiedAt = null): User
{
    return User::reconstitute(
        UserId::generate(),
        new EmailAddress('member@example.com'),
        new PasswordHash(password_hash('existing-password', PASSWORD_BCRYPT)),
        new DateTimeImmutable('2026-09-01T08:00:00+00:00'),
        1,
        $verifiedAt,
    );
}

it('stores a token hash and sends the raw token to an unverified user', function (): void {
    $user = emailVerificationTestUser();
    $rawToken = new EmailVerificationToken(str_repeat('b', 64));
    $storedRequest = null;

    $users = Mockery::mock(UserRepository::class);
    $users->shouldReceive('findById')->once()->with($user->id())->andReturn($user);

    $requests = Mockery::mock(EmailVerificationRequestRepository::class);
    $requests->shouldReceive('replace')->once()->with(Mockery::on(
        function (EmailVerificationRequest $request) use (&$storedRequest): bool {
            $storedRequest = $request;

            return true;
        },
    ));

    $notifier = Mockery::mock(EmailVerificationNotifier::class);
    $notifier->shouldReceive('send')->once()->with(
        $user->email(),
        $rawToken,
        Mockery::on(fn (DateTimeImmutable $time): bool => $time == new DateTimeImmutable('2026-09-18T10:00:00+00:00')),
    );

    $service = new RequestEmailVerification(
        $users,
        $requests,
        new EmailVerificationTestTokenGenerator($rawToken),
        $notifier,
        new EmailVerificationTestClock(new DateTimeImmutable('2026-09-17T10:00:00+00:00')),
        new EmailVerificationTestTransactionManager,
    );

    $service->handle(new RequestEmailVerificationCommand($user->id()));

    expect($storedRequest)->toBeInstanceOf(EmailVerificationRequest::class)
        ->and($storedRequest?->tokenHash()->value())->toBe(hash('sha256', $rawToken->value()));
});

it('does not issue another token to a verified user', function (): void {
    $user = emailVerificationTestUser(new DateTimeImmutable('2026-09-17T09:00:00+00:00'));

    $users = Mockery::mock(UserRepository::class);
    $users->shouldReceive('findById')->once()->andReturn($user);

    $service = new RequestEmailVerification(
        $users,
        Mockery::mock(EmailVerificationRequestRepository::class),
        Mockery::mock(EmailVerificationTokenGenerator::class),
        Mockery::mock(EmailVerificationNotifier::class),
        new EmailVerificationTestClock(new DateTimeImmutable('2026-09-17T10:00:00+00:00')),
        new EmailVerificationTestTransactionManager,
    );

    $service->handle(new RequestEmailVerificationCommand($user->id()));

    expect($user->isEmailVerified())->toBeTrue();
});

it('verifies the user, consumes the token, and publishes the event', function (): void {
    $user = emailVerificationTestUser();
    $rawToken = new EmailVerificationToken(str_repeat('c', 64));
    $tokenHash = EmailVerificationTokenHash::fromToken($rawToken);
    $request = new EmailVerificationRequest(
        $user->id(),
        $tokenHash,
        new DateTimeImmutable('2026-09-17T10:00:00+00:00'),
        new DateTimeImmutable('2026-09-18T10:00:00+00:00'),
    );

    $requests = Mockery::mock(EmailVerificationRequestRepository::class);
    $requests->shouldReceive('findByTokenHash')->once()->andReturn($request);
    $requests->shouldReceive('consume')->once()->with(Mockery::on(
        fn (EmailVerificationTokenHash $hash): bool => $hash->equals($tokenHash),
    ));

    $users = Mockery::mock(UserRepository::class);
    $users->shouldReceive('findById')->once()->with($user->id())->andReturn($user);
    $users->shouldReceive('save')->once()->with($user);

    $uuidGenerator = Mockery::mock(UuidGenerator::class);
    $uuidGenerator->shouldReceive('generate')->once()->andReturn(Uuid::generate());

    $publisher = new EmailVerificationTestEventPublisher;
    $service = new VerifyEmail(
        $requests,
        $users,
        new EmailVerificationTestClock(new DateTimeImmutable('2026-09-17T10:15:00+00:00')),
        $uuidGenerator,
        new EmailVerificationTestTransactionManager,
        $publisher,
    );

    $service->handle(new VerifyEmailCommand($rawToken->value()));

    expect($user->isEmailVerified())->toBeTrue()
        ->and($user->version())->toBe(2)
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(UserEmailVerified::class);
});

it('rejects an expired verification token', function (): void {
    $rawToken = new EmailVerificationToken(str_repeat('d', 64));
    $request = new EmailVerificationRequest(
        UserId::generate(),
        EmailVerificationTokenHash::fromToken($rawToken),
        new DateTimeImmutable('2026-09-17T10:00:00+00:00'),
        new DateTimeImmutable('2026-09-17T11:00:00+00:00'),
    );

    $requests = Mockery::mock(EmailVerificationRequestRepository::class);
    $requests->shouldReceive('findByTokenHash')->once()->andReturn($request);

    $service = new VerifyEmail(
        $requests,
        Mockery::mock(UserRepository::class),
        new EmailVerificationTestClock(new DateTimeImmutable('2026-09-17T11:00:00+00:00')),
        Mockery::mock(UuidGenerator::class),
        new EmailVerificationTestTransactionManager,
        new EmailVerificationTestEventPublisher,
    );

    $service->handle(new VerifyEmailCommand($rawToken->value()));
})->throws(InvalidEmailVerificationToken::class, 'The email-verification token is invalid or has expired.');

it('hides the reason a malformed verification token is rejected', function (): void {
    $service = new VerifyEmail(
        Mockery::mock(EmailVerificationRequestRepository::class),
        Mockery::mock(UserRepository::class),
        new EmailVerificationTestClock(new DateTimeImmutable('2026-09-17T10:15:00+00:00')),
        Mockery::mock(UuidGenerator::class),
        new EmailVerificationTestTransactionManager,
        new EmailVerificationTestEventPublisher,
    );

    $service->handle(new VerifyEmailCommand('not-a-valid-token'));
})->throws(InvalidEmailVerificationToken::class, 'The email-verification token is invalid or has expired.');
