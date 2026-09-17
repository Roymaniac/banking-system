<?php

declare(strict_types=1);

use Identity\Domain\PasswordReset\PasswordResetRequest;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetToken;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetTokenHash;
use Identity\Domain\User\ValueObject\EmailAddress;

it('stores only the hash of a reset token and tracks its expiry', function (): void {
    $token = new PasswordResetToken(str_repeat('a', 64));
    $requestedAt = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
    $expiresAt = new DateTimeImmutable('2026-09-16T10:30:00+00:00');

    $request = new PasswordResetRequest(
        new EmailAddress('member@example.com'),
        PasswordResetTokenHash::fromToken($token),
        $requestedAt,
        $expiresAt,
    );

    expect($request->tokenHash()->value())->toBe(hash('sha256', $token->value()))
        ->and($request->isExpiredAt(new DateTimeImmutable('2026-09-16T10:29:59+00:00')))->toBeFalse()
        ->and($request->isExpiredAt($expiresAt))->toBeTrue();
});

it('rejects a request whose expiry is not after its creation', function (): void {
    $time = new DateTimeImmutable('2026-09-16T10:00:00+00:00');

    new PasswordResetRequest(
        new EmailAddress('member@example.com'),
        new PasswordResetTokenHash(str_repeat('b', 64)),
        $time,
        $time,
    );
})->throws(InvalidArgumentException::class);
