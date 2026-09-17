<?php

declare(strict_types=1);

use Customer\Domain\Customer\ValueObject\DateOfBirth;

it('calculates age using calendar dates', function (): void {
    $birthDate = DateOfBirth::fromString(
        '2000-09-18',
        new DateTimeImmutable('2026-09-17T10:00:00+01:00'),
    );

    expect($birthDate->ageAt(new DateTimeImmutable('2026-09-17')))->toBe(25)
        ->and($birthDate->ageAt(new DateTimeImmutable('2026-09-18')))->toBe(26);
});

it('rejects an impossible calendar date', function (): void {
    DateOfBirth::fromString('2026-02-30', new DateTimeImmutable('2026-09-17'));
})->throws(InvalidArgumentException::class, 'The date of birth must be a real date');

it('rejects a future birth date', function (): void {
    DateOfBirth::fromString('2026-09-18', new DateTimeImmutable('2026-09-17'));
})->throws(InvalidArgumentException::class, 'The date of birth cannot be in the future.');
