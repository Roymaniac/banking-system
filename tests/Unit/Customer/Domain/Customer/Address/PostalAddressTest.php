<?php

declare(strict_types=1);

use Customer\Domain\Customer\Address\ValueObject\CountryCode;
use Customer\Domain\Customer\Address\ValueObject\PostalAddress;

it('normalizes a postal address without changing meaningful characters', function (): void {
    $address = new PostalAddress(
        '  14   Broad Street ',
        ' Flat  2B ',
        ' Lagos Island ',
        ' Lagos ',
        ' 100001 ',
        new CountryCode('ng'),
    );

    expect($address->lineOne())->toBe('14 Broad Street')
        ->and($address->lineTwo())->toBe('Flat 2B')
        ->and($address->postalCode())->toBe('100001')
        ->and($address->countryCode()->value())->toBe('NG');
});

it('requires the address fields needed for delivery', function (): void {
    new PostalAddress('', null, 'Lagos', 'Lagos', '100001', new CountryCode('NG'));
})->throws(InvalidArgumentException::class, 'Address line one, city, state or region, and postal code are required.');

it('rejects a malformed country code', function (): void {
    new CountryCode('Nigeria');
})->throws(InvalidArgumentException::class, 'The country code must contain exactly two letters.');
