<?php

declare(strict_types=1);

use Identity\Application\Authentication\PasswordHasher;
use Identity\Infrastructure\Authentication\LaravelPasswordHasher;
use Tests\TestCase;

uses(TestCase::class);

it('hashes and verifies passwords through Laravel', function (): void {
    $passwordHasher = app(PasswordHasher::class);
    $hash = $passwordHasher->hash('correct-password');

    expect($passwordHasher)->toBeInstanceOf(LaravelPasswordHasher::class)
        ->and($passwordHasher->verify('correct-password', $hash))->toBeTrue()
        ->and($passwordHasher->verify('wrong-password', $hash))->toBeFalse()
        ->and($passwordHasher->verify('correct-password', null))->toBeFalse()
        ->and($passwordHasher->needsRehash($hash))->toBeFalse();
});
