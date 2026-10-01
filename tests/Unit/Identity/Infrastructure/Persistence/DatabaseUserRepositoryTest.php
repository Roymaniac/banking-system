<?php

declare(strict_types=1);

use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\User;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\PasswordHash;
use Identity\Domain\User\ValueObject\UserId;
use Identity\Infrastructure\Persistence\DatabaseUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('binds and round-trips UUID Identity users through the Laravel users table', function (): void {
    $repository = app(UserRepository::class);
    $user = User::register(
        UserId::generate(),
        new EmailAddress('identity@example.com'),
        new PasswordHash(password_hash('correct-password', PASSWORD_BCRYPT)),
        new DateTimeImmutable('2026-09-30T10:00:00+01:00'),
        Uuid::generate(),
    );
    $repository->save($user);
    $stored = $repository->findByEmail(new EmailAddress('IDENTITY@example.com'));

    expect($repository)->toBeInstanceOf(DatabaseUserRepository::class)
        ->and($stored)->not->toBeNull()
        ->and($stored->id()->equals($user->id()))->toBeTrue()
        ->and($stored->email()->value())->toBe('identity@example.com')
        ->and($stored->version())->toBe(1);
});
