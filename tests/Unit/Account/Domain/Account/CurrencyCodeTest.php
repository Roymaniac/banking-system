<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\CurrencyCode;

it('normalizes a three-letter currency code', function (): void {
    expect((new CurrencyCode(' ngn '))->value())->toBe('NGN');
});

it('rejects a malformed currency code', function (): void {
    new CurrencyCode('naira');
})->throws(InvalidArgumentException::class, 'The currency code must contain exactly three letters.');
