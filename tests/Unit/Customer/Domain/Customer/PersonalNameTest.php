<?php

declare(strict_types=1);

use Customer\Domain\Customer\ValueObject\PersonalName;

it('cleans extra spaces while preserving international name characters', function (): void {
    $name = new PersonalName('  Chiamaka  ', '  María   José ', '  O’Nwosu  ');

    expect($name->firstName())->toBe('Chiamaka')
        ->and($name->middleName())->toBe('María José')
        ->and($name->lastName())->toBe('O’Nwosu');
});

it('treats a blank middle name as absent', function (): void {
    expect((new PersonalName('Ada', '   ', 'Lovelace'))->middleName())->toBeNull();
});

it('requires both a first name and a last name', function (): void {
    new PersonalName('', null, 'Lovelace');
})->throws(InvalidArgumentException::class, 'A first name and last name are required.');
