<?php

declare(strict_types=1);

namespace Customer\Application\Address;

use Customer\Domain\Customer\Address\ValueObject\AddressType;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries address input into the add-address use case. */
final readonly class AddCustomerAddressCommand
{
    public function __construct(
        public CustomerId $customerId,
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
