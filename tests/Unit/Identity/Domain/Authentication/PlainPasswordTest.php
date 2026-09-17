<?php

declare(strict_types=1);

use Identity\Domain\Authentication\ValueObject\PlainPassword;

it('accepts a password within the supported length', function (): void {
    expect((new PlainPassword('a-secure-passphrase'))->value())->toBe('a-secure-passphrase');
});

it('rejects a password shorter than twelve characters', function (): void {
    new PlainPassword('too-short');
})->throws(InvalidArgumentException::class, 'A password must contain between 12 and 128 characters.');
