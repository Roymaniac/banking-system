<?php

declare(strict_types=1);

use Identity\Domain\EmailVerification\EmailVerificationRequest;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationToken;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationTokenHash;
use Identity\Domain\User\ValueObject\UserId;
use Identity\Infrastructure\EmailVerification\DatabaseEmailVerificationRequestRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('stores, retrieves, and consumes an email-verification request', function (): void {
    $repository = app(DatabaseEmailVerificationRequestRepository::class);
    $tokenHash = EmailVerificationTokenHash::fromToken(
        new EmailVerificationToken(str_repeat('e', 64)),
    );
    $request = new EmailVerificationRequest(
        UserId::generate(),
        $tokenHash,
        new DateTimeImmutable('2026-09-17T10:00:00+00:00'),
        new DateTimeImmutable('2026-09-18T10:00:00+00:00'),
    );

    $repository->replace($request);
    $stored = $repository->findByTokenHash($tokenHash);

    expect($stored?->userId()->equals($request->userId()))->toBeTrue()
        ->and($stored?->expiresAt())->toEqual($request->expiresAt());

    $repository->consume($tokenHash);

    expect($repository->findByTokenHash($tokenHash))->toBeNull();
});
