<?php

declare(strict_types=1);

use Customer\Domain\Customer\Address\ValueObject\AddressId;
use Customer\Domain\Customer\Address\ValueObject\AddressType;
use Customer\Domain\Customer\Address\ValueObject\CountryCode;
use Customer\Domain\Customer\Address\ValueObject\PostalAddress;
use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Event\CustomerAddressAdded;
use Customer\Domain\Customer\Event\CustomerAddressUpdated;
use Customer\Domain\Customer\Exception\CustomerAddressNotFound;
use Customer\Domain\Customer\Exception\DuplicateCustomerAddress;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Identifier\Uuid;

function addressTestCustomer(): Customer
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

function addressTestDetails(string $lineOne = '14 Broad Street'): PostalAddress
{
    return new PostalAddress($lineOne, null, 'Lagos Island', 'Lagos', '100001', new CountryCode('NG'));
}

it('adds an address and records an event without private location details', function (): void {
    $customer = addressTestCustomer();
    $addressId = AddressId::generate();
    $customer->addAddress(
        $addressId,
        AddressType::Residential,
        addressTestDetails(),
        new DateTimeImmutable('2026-09-17T11:00:00+01:00'),
        Uuid::generate(),
    );

    $event = $customer->recordedEvents()[0];

    expect($customer->addresses())->toHaveCount(1)
        ->and($customer->version())->toBe(2)
        ->and($event)->toBeInstanceOf(CustomerAddressAdded::class)
        ->and($event->payload())->toBe([
            'address_id' => $addressId->value(),
            'address_type' => 'residential',
        ])
        ->and($event->payload())->not->toHaveKeys(['line_one', 'postal_code']);
});

it('does not add the same physical address twice', function (): void {
    $customer = addressTestCustomer();
    $customer->addAddress(AddressId::generate(), AddressType::Residential, addressTestDetails(), new DateTimeImmutable, Uuid::generate());
    $customer->addAddress(AddressId::generate(), AddressType::Mailing, addressTestDetails(), new DateTimeImmutable, Uuid::generate());
})->throws(DuplicateCustomerAddress::class, 'This address is already registered');

it('updates an address while preserving its ID', function (): void {
    $customer = addressTestCustomer();
    $addressId = AddressId::generate();
    $customer->addAddress($addressId, AddressType::Residential, addressTestDetails(), new DateTimeImmutable, Uuid::generate());
    $customer->pullDomainEvents();

    $customer->updateAddress(
        $addressId,
        AddressType::Mailing,
        addressTestDetails('22 Marina Road'),
        new DateTimeImmutable,
        Uuid::generate(),
    );

    expect($customer->addresses()[0]->id()->equals($addressId))->toBeTrue()
        ->and($customer->addresses()[0]->type())->toBe(AddressType::Mailing)
        ->and($customer->addresses()[0]->details()->lineOne())->toBe('22 Marina Road')
        ->and($customer->recordedEvents()[0])->toBeInstanceOf(CustomerAddressUpdated::class);
});

it('rejects an update for an unknown address ID', function (): void {
    addressTestCustomer()->updateAddress(
        AddressId::generate(),
        AddressType::Mailing,
        addressTestDetails(),
        new DateTimeImmutable,
        Uuid::generate(),
    );
})->throws(CustomerAddressNotFound::class, 'The requested customer address does not exist.');
