<?php

declare(strict_types=1);

use Identity\Infrastructure\PasswordReset\SecurePasswordResetTokenGenerator;

it('generates unique 256-bit hexadecimal reset tokens', function (): void {
    $generator = new SecurePasswordResetTokenGenerator;
    $first = $generator->generate();
    $second = $generator->generate();

    expect($first->value())->toMatch('/^[a-f0-9]{64}$/')
        ->and($second->value())->not->toBe($first->value());
});
