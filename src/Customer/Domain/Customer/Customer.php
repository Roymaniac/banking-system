<?php

declare(strict_types=1);

namespace Customer\Domain\Customer;

use Customer\Domain\Customer\Address\CustomerAddress;
use Customer\Domain\Customer\Address\ValueObject\AddressId;
use Customer\Domain\Customer\Address\ValueObject\AddressType;
use Customer\Domain\Customer\Address\ValueObject\PostalAddress;
use Customer\Domain\Customer\Contact\CustomerContact;
use Customer\Domain\Customer\Contact\ValueObject\ContactId;
use Customer\Domain\Customer\Contact\ValueObject\ContactPoint;
use Customer\Domain\Customer\Event\CustomerAddressAdded;
use Customer\Domain\Customer\Event\CustomerAddressUpdated;
use Customer\Domain\Customer\Event\CustomerContactAdded;
use Customer\Domain\Customer\Event\CustomerContactUpdated;
use Customer\Domain\Customer\Event\CustomerProfileCreated;
use Customer\Domain\Customer\Exception\CustomerAddressNotFound;
use Customer\Domain\Customer\Exception\CustomerContactNotFound;
use Customer\Domain\Customer\Exception\DuplicateCustomerAddress;
use Customer\Domain\Customer\Exception\DuplicateCustomerContact;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/**
 * The Customer aggregate owns personal profile information used by banking.
 */
final class Customer extends AggregateRoot
{
    /** @var list<CustomerAddress> */
    private array $addresses;

    /** @var list<CustomerContact> */
    private array $contacts;

    private function __construct(
        private readonly CustomerId $id,
        private readonly UserId $userId,
        private PersonalName $name,
        private DateOfBirth $dateOfBirth,
        private readonly DateTimeImmutable $registeredAt,
        array $addresses = [],
        array $contacts = [],
    ) {
        $this->addresses = $addresses;
        $this->contacts = $contacts;
    }

    public static function create(
        CustomerId $id,
        UserId $userId,
        PersonalName $name,
        DateOfBirth $dateOfBirth,
        DateTimeImmutable $registeredAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): self {
        $customer = new self($id, $userId, $name, $dateOfBirth, $registeredAt);
        $customer->record(new CustomerProfileCreated(
            $eventId,
            $id,
            $registeredAt,
            $userId,
            $correlationId,
        ));

        return $customer;
    }

    /** Rebuilds a stored profile without creating a second creation event. */
    public static function reconstitute(
        CustomerId $id,
        UserId $userId,
        PersonalName $name,
        DateOfBirth $dateOfBirth,
        DateTimeImmutable $registeredAt,
        int $version,
        array $addresses = [],
        array $contacts = [],
    ): self {
        $customer = new self($id, $userId, $name, $dateOfBirth, $registeredAt, $addresses, $contacts);
        $customer->reconstituteAtVersion($version);

        return $customer;
    }

    public function id(): CustomerId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function name(): PersonalName
    {
        return $this->name;
    }

    public function dateOfBirth(): DateOfBirth
    {
        return $this->dateOfBirth;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    /** @return list<CustomerAddress> */
    public function addresses(): array
    {
        return $this->addresses;
    }

    /** @return list<CustomerContact> */
    public function contacts(): array
    {
        return $this->contacts;
    }

    public function addContact(
        ContactId $contactId,
        ContactPoint $contactPoint,
        DateTimeImmutable $addedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        $this->guardAgainstDuplicateContact($contactPoint);
        $this->contacts[] = new CustomerContact($contactId, $contactPoint);

        $this->record(new CustomerContactAdded(
            $eventId,
            $this->id,
            $this->version() + 1,
            $addedAt,
            $contactId,
            $contactPoint->type(),
            $correlationId,
        ));
    }

    public function updateContact(
        ContactId $contactId,
        ContactPoint $contactPoint,
        DateTimeImmutable $updatedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        $contact = $this->findContact($contactId);

        foreach ($this->contacts as $existingContact) {
            if (! $existingContact->id()->equals($contactId) && $existingContact->contactPoint()->equals($contactPoint)) {
                throw DuplicateCustomerContact::create();
            }
        }

        $contact->update($contactPoint);
        $this->record(new CustomerContactUpdated(
            $eventId,
            $this->id,
            $this->version() + 1,
            $updatedAt,
            $contactId,
            $contactPoint->type(),
            $correlationId,
        ));
    }

    public function addAddress(
        AddressId $addressId,
        AddressType $type,
        PostalAddress $details,
        DateTimeImmutable $addedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        $this->guardAgainstDuplicateAddress($details);
        $this->addresses[] = new CustomerAddress($addressId, $type, $details);

        $this->record(new CustomerAddressAdded(
            $eventId,
            $this->id,
            $this->version() + 1,
            $addedAt,
            $addressId,
            $type,
            $correlationId,
        ));
    }

    public function updateAddress(
        AddressId $addressId,
        AddressType $type,
        PostalAddress $details,
        DateTimeImmutable $updatedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        $address = $this->findAddress($addressId);

        foreach ($this->addresses as $existingAddress) {
            if (! $existingAddress->id()->equals($addressId) && $existingAddress->details()->equals($details)) {
                throw DuplicateCustomerAddress::create();
            }
        }

        $address->update($type, $details);
        $this->record(new CustomerAddressUpdated(
            $eventId,
            $this->id,
            $this->version() + 1,
            $updatedAt,
            $addressId,
            $type,
            $correlationId,
        ));
    }

    private function guardAgainstDuplicateAddress(PostalAddress $details): void
    {
        foreach ($this->addresses as $address) {
            if ($address->details()->equals($details)) {
                throw DuplicateCustomerAddress::create();
            }
        }
    }

    private function findAddress(AddressId $addressId): CustomerAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->id()->equals($addressId)) {
                return $address;
            }
        }

        throw CustomerAddressNotFound::create();
    }

    private function guardAgainstDuplicateContact(ContactPoint $contactPoint): void
    {
        foreach ($this->contacts as $contact) {
            if ($contact->contactPoint()->equals($contactPoint)) {
                throw DuplicateCustomerContact::create();
            }
        }
    }

    private function findContact(ContactId $contactId): CustomerContact
    {
        foreach ($this->contacts as $contact) {
            if ($contact->id()->equals($contactId)) {
                return $contact;
            }
        }

        throw CustomerContactNotFound::create();
    }
}
