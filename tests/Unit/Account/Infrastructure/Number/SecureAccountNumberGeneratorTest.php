<?php

declare(strict_types=1);

use Account\Application\Number\AccountNumberGenerator;
use Account\Infrastructure\Number\SecureAccountNumberGenerator;
use Tests\TestCase;

uses(TestCase::class);

it('binds the number generator contract to the secure implementation', function (): void {
    expect(app(AccountNumberGenerator::class))->toBeInstanceOf(SecureAccountNumberGenerator::class);
});

it('generates valid ten-digit account numbers', function (): void {
    $number = (new SecureAccountNumberGenerator)->generate();

    expect($number->value())->toMatch('/^[1-9][0-9]{9}$/');
});
