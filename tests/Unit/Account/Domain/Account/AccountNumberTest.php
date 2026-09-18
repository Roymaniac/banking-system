<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountNumber;

it('accepts a ten-digit account number and exposes only its last four when needed', function (): void {
    $number = new AccountNumber('1234567890');

    expect($number->value())->toBe('1234567890')
        ->and($number->lastFour())->toBe('7890');
});

it('rejects an account number with the wrong length', function (): void {
    new AccountNumber('12345');
})->throws(InvalidArgumentException::class, 'An account number must contain exactly 10 digits');

it('rejects an account number that starts with zero', function (): void {
    new AccountNumber('0234567890');
})->throws(InvalidArgumentException::class, 'cannot start with zero');
