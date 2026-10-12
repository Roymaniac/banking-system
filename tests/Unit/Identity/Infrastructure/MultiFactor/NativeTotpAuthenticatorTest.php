<?php

declare(strict_types=1);

use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Infrastructure\MultiFactor\NativeTotpAuthenticator;

it('verifies a standard TOTP test vector and rejects malformed codes', function (): void {
    $totp = new NativeTotpAuthenticator;
    $at = (new DateTimeImmutable('@59'))->setTimezone(new DateTimeZone('UTC'));

    expect($totp->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', $at))->toBeTrue()
        ->and($totp->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287083', $at))->toBeFalse()
        ->and($totp->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'not-code', $at))->toBeFalse();
});

it('generates an authenticator-compatible secret and provisioning URI', function (): void {
    $totp = new NativeTotpAuthenticator;
    $secret = $totp->generateSecret();
    $uri = $totp->provisioningUri(
        new EmailAddress('member@example.com'),
        'Banking System',
        $secret,
    );

    expect($secret)->toMatch('/^[A-Z2-7]{32}$/')
        ->and($uri)->toStartWith('otpauth://totp/')
        ->and($uri)->toContain('secret='.$secret, 'issuer=Banking%20System');
});
