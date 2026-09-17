<?php

declare(strict_types=1);

namespace Customer\Application\Address;

use Customer\Domain\Customer\Address\ValueObject\AddressId;
use Customer\Domain\Customer\Address\ValueObject\AddressType;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries replacement details for one existing customer address. */
final readonly class UpdateCustomerAddressCommand
{
    public function __construct(
        public CustomerId $customerId,
        public AddressId $addressId,
        public AddressType $type,
        public string $lineOne,
        public ?string $lineTwo,
        public string $city,
        public string $stateOrRegion,
        public string $postalCode,
        public string $countryCode,
        public ?CorrelationId $correlationId = null,
    ) {}
}
