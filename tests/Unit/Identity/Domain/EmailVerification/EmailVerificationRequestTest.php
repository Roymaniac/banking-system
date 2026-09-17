<?php

declare(strict_types=1);

use Identity\Domain\EmailVerification\EmailVerificationRequest;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationToken;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationTokenHash;
use Identity\Domain\User\ValueObject\UserId;

it('stores only the token hash and tracks expiry', function (): void {
    $token = new EmailVerificationToken(str_repeat('a', 64));
    $expiresAt = new DateTimeImmutable('2026-09-18T10:00:00+00:00');
    $request = new EmailVerificationRequest(
        UserId::generate(),
        EmailVerificationTokenHash::fromToken($token),
        new DateTimeImmutable('2026-09-17T10:00:00+00:00'),
        $expiresAt,
    );

    expect($request->tokenHash()->value())->toBe(hash('sha256', $token->value()))
        ->and($request->isExpiredAt(new DateTimeImmutable('2026-09-18T09:59:59+00:00')))->toBeFalse()
        ->and($request->isExpiredAt($expiresAt))->toBeTrue();
});
