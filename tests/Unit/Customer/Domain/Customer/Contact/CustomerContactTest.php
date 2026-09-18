<?php

declare(strict_types=1);

use Customer\Domain\Customer\Contact\ValueObject\ContactId;
use Customer\Domain\Customer\Contact\ValueObject\ContactPoint;
use Customer\Domain\Customer\Contact\ValueObject\ContactType;
use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Event\CustomerContactAdded;
use Customer\Domain\Customer\Event\CustomerContactUpdated;
use Customer\Domain\Customer\Exception\CustomerContactNotFound;
use Customer\Domain\Customer\Exception\DuplicateCustomerContact;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Identifier\Uuid;

function contactTestCustomer(): Customer
{
    $now = new DateTimeImmutable('2026-09-17T10:00:00+01:00');

    return Customer::reconstitute(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', null, 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        1,
    );
}

it('adds a contact and records an event without exposing its value', function (): void {
    $customer = contactTestCustomer();
    $contactId = ContactId::generate();
    $customer->addContact(
        $contactId,
        new ContactPoint(ContactType::Email, 'ada@example.com'),
        new DateTimeImmutable,
        Uuid::generate(),
    );

    $event = $customer->recordedEvents()[0];

    expect($customer->contacts())->toHaveCount(1)
        ->and($customer->version())->toBe(2)
        ->and($event)->toBeInstanceOf(CustomerContactAdded::class)
        ->and($event->payload())->toBe([
            'contact_id' => $contactId->value(),
            'contact_type' => 'email',
        ])
        ->and($event->payload())->not->toHaveKey('value');
});

it('blocks the same normalized contact from being added twice', function (): void {
    $customer = contactTestCustomer();
    $customer->addContact(ContactId::generate(), new ContactPoint(ContactType::Email, 'ADA@example.com'), new DateTimeImmutable, Uuid::generate());
    $customer->addContact(ContactId::generate(), new ContactPoint(ContactType::Email, 'ada@example.com'), new DateTimeImmutable, Uuid::generate());
})->throws(DuplicateCustomerContact::class, 'This contact is already registered');

it('updates a contact while preserving its ID', function (): void {
    $customer = contactTestCustomer();
    $contactId = ContactId::generate();
    $customer->addContact($contactId, new ContactPoint(ContactType::Phone, '+2348012345678'), new DateTimeImmutable, Uuid::generate());
    $customer->pullDomainEvents();

    $customer->updateContact(
        $contactId,
        new ContactPoint(ContactType::Phone, '+2348098765432'),
        new DateTimeImmutable,
        Uuid::generate(),
    );

    expect($customer->contacts()[0]->id()->equals($contactId))->toBeTrue()
        ->and($customer->contacts()[0]->contactPoint()->value())->toBe('+2348098765432')
        ->and($customer->recordedEvents()[0])->toBeInstanceOf(CustomerContactUpdated::class);
});

it('rejects an update for an unknown contact ID', function (): void {
    contactTestCustomer()->updateContact(
        ContactId::generate(),
        new ContactPoint(ContactType::Email, 'ada@example.com'),
        new DateTimeImmutable,
        Uuid::generate(),
    );
})->throws(CustomerContactNotFound::class, 'The requested customer contact does not exist.');
