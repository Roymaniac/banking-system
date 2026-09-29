<?php

declare(strict_types=1);

namespace Reporting\Application\Customer\View;

/** Read-only address data prepared for a customer report. */
final readonly class CustomerAddressView
{
    public function __construct(
        public string $type,
        public string $lineOne,
        public ?string $lineTwo,
        public string $city,
        public string $stateOrRegion,
        public string $postalCode,
        public string $countryCode,
    ) {}
}
