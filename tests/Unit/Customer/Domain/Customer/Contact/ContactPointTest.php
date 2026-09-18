<?php

declare(strict_types=1);

use Customer\Domain\Customer\Contact\ValueObject\ContactPoint;
use Customer\Domain\Customer\Contact\ValueObject\ContactType;

it('normalizes an email contact', function (): void {
    $contact = new ContactPoint(ContactType::Email, '  Ada@Example.COM ');

    expect($contact->value())->toBe('ada@example.com');
});

it('normalizes an international phone contact', function (): void {
    $contact = new ContactPoint(ContactType::Phone, '+234 (801) 234-5678');

    expect($contact->value())->toBe('+2348012345678');
});

it('rejects an invalid email contact', function (): void {
    new ContactPoint(ContactType::Email, 'not-an-email');
})->throws(InvalidArgumentException::class, 'The contact email address is not valid.');

it('requires a country code on phone contacts', function (): void {
    new ContactPoint(ContactType::Phone, '08012345678');
})->throws(InvalidArgumentException::class, 'The phone number must use international format');
