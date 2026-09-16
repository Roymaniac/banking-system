<?php

declare(strict_types=1);

use Identity\Domain\PasswordReset\PasswordResetRequest;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetTokenHash;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Infrastructure\PasswordReset\DatabasePasswordResetRequestRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('stores, retrieves, and consumes a password-reset request', function (): void {
    $repository = app(DatabasePasswordResetRequestRepository::class);
    $tokenHash = PasswordResetTokenHash::fromToken(
        new PasswordResetToken(str_repeat('1', 64)),
    );
    $request = new PasswordResetRequest(
        new EmailAddress('member@example.com'),
        $tokenHash,
        new DateTimeImmutable('2026-09-16T10:00:00+00:00'),
        new DateTimeImmutable('2026-09-16T10:30:00+00:00'),
    );

    $repository->replace($request);
    $stored = $repository->findByTokenHash($tokenHash);

    expect($stored?->email()->value())->toBe('member@example.com')
        ->and($stored?->expiresAt())->toEqual($request->expiresAt());

    $repository->consume($tokenHash);

    expect($repository->findByTokenHash($tokenHash))->toBeNull();
});
