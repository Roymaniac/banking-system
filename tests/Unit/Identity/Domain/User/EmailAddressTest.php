<?php

declare(strict_types=1);

use Identity\Domain\User\ValueObject\EmailAddress;

it('normalizes an email address before storing it', function (): void {
    $email = new EmailAddress('  Ada.Lovelace@Example.COM  ');

    expect($email->value())->toBe('ada.lovelace@example.com');
});

it('rejects an invalid email address', function (): void {
    new EmailAddress('not-an-email');
})->throws(InvalidArgumentException::class, 'The email address is not valid.');
