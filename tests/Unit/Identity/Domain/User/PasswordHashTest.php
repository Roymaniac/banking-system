<?php

declare(strict_types=1);

use Identity\Domain\User\ValueObject\PasswordHash;

it('accepts and compares recognized password hashes', function (): void {
    $value = password_hash('correct-horse-battery-staple', PASSWORD_BCRYPT);

    expect((new PasswordHash($value))->equals(new PasswordHash($value)))->toBeTrue();
});

it('rejects plain-text passwords', function (): void {
    new PasswordHash('plain-text-password');
})->throws(InvalidArgumentException::class, 'A recognized password hash is required.');
