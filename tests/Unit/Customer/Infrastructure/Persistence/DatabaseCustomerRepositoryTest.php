<?php

declare(strict_types=1);

use Customer\Domain\Customer\Address\ValueObject\AddressId;
use Customer\Domain\Customer\Address\ValueObject\AddressType;
use Customer\Domain\Customer\Address\ValueObject\CountryCode;
use Customer\Domain\Customer\Address\ValueObject\PostalAddress;
use Customer\Domain\Customer\Contact\ValueObject\ContactId;
use Customer\Domain\Customer\Contact\ValueObject\ContactPoint;
use Customer\Domain\Customer\Contact\ValueObject\ContactType;
use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use Customer\Infrastructure\Persistence\DatabaseCustomerRepository;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('binds the customer repository contract to its database adapter', function (): void {
    expect(app(CustomerRepository::class))->toBeInstanceOf(DatabaseCustomerRepository::class);
});

it('stores and retrieves a customer by both customer and user ID', function (): void {
    $repository = app(DatabaseCustomerRepository::class);
    $now = new DateTimeImmutable('2026-09-17T10:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', 'Byron', 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        Uuid::generate(),
    );

    $repository->save($customer);
    $byId = $repository->findById($customer->id());
    $byUser = $repository->findByUserId($customer->userId());

    expect($byId?->id()->equals($customer->id()))->toBeTrue()
        ->and($byId?->name()->middleName())->toBe('Byron')
        ->and($byId?->dateOfBirth()->value())->toBe('2000-01-01')
        ->and($byId?->recordedEvents())->toBeEmpty()
        ->and($byUser?->id()->equals($customer->id()))->toBeTrue()
        ->and($repository->existsForUser($customer->userId()))->toBeTrue();
});

it('stores and updates addresses as part of the customer aggregate', function (): void {
    $repository = app(DatabaseCustomerRepository::class);
    $now = new DateTimeImmutable('2026-09-17T10:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', null, 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        Uuid::generate(),
    );
    $repository->save($customer);
    $customer->pullDomainEvents();

    $addressId = AddressId::generate();
    $customer->addAddress(
        $addressId,
        AddressType::Residential,
        new PostalAddress('14 Broad Street', null, 'Lagos Island', 'Lagos', '100001', new CountryCode('NG')),
        $now,
        Uuid::generate(),
    );
    $repository->save($customer);

    $stored = $repository->findById($customer->id());

    expect($stored?->addresses())->toHaveCount(1)
        ->and($stored?->addresses()[0]->id()->equals($addressId))->toBeTrue()
        ->and($stored?->addresses()[0]->details()->lineOne())->toBe('14 Broad Street')
        ->and($stored?->recordedEvents())->toBeEmpty();

    $stored?->updateAddress(
        $addressId,
        AddressType::Mailing,
        new PostalAddress('22 Marina Road', null, 'Lagos Island', 'Lagos', '100002', new CountryCode('NG')),
        $now,
        Uuid::generate(),
    );
    $repository->save($stored);

    $updated = $repository->findById($customer->id());

    expect($updated?->addresses()[0]->type())->toBe(AddressType::Mailing)
        ->and($updated?->addresses()[0]->details()->lineOne())->toBe('22 Marina Road')
        ->and($updated?->version())->toBe(3);
});

it('stores and updates contacts as part of the customer aggregate', function (): void {
    $repository = app(DatabaseCustomerRepository::class);
    $now = new DateTimeImmutable('2026-09-17T10:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', null, 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        Uuid::generate(),
    );
    $repository->save($customer);
    $customer->pullDomainEvents();

    $contactId = ContactId::generate();
    $customer->addContact(
        $contactId,
        new ContactPoint(ContactType::Email, 'ADA@Example.com'),
        $now,
        Uuid::generate(),
    );
    $repository->save($customer);

    $stored = $repository->findById($customer->id());

    expect($stored?->contacts())->toHaveCount(1)
        ->and($stored?->contacts()[0]->id()->equals($contactId))->toBeTrue()
        ->and($stored?->contacts()[0]->contactPoint()->value())->toBe('ada@example.com')
        ->and($stored?->recordedEvents())->toBeEmpty();

    $stored?->updateContact(
        $contactId,
        new ContactPoint(ContactType::Phone, '+2348012345678'),
        $now,
        Uuid::generate(),
    );
    $repository->save($stored);

    $updated = $repository->findById($customer->id());

    expect($updated?->contacts()[0]->contactPoint()->type())->toBe(ContactType::Phone)
        ->and($updated?->contacts()[0]->contactPoint()->value())->toBe('+2348012345678')
        ->and($updated?->version())->toBe(3);
});
