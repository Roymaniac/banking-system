<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use Customer\Domain\Customer\Address\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Defines the JSON shape of one customer address.
 *
 * @mixin CustomerAddress
 */
final class CustomerAddressResource extends JsonResource
{
    /** @return array<string, string|null> */
    public function toArray(Request $request): array
    {
        /** @var CustomerAddress $address */
        $address = $this->resource;

        return [
            'id' => $address->id()->value(),
            'type' => $address->type()->value,
            'line_one' => $address->details()->lineOne(),
            'line_two' => $address->details()->lineTwo(),
            'city' => $address->details()->city(),
            'state_or_region' => $address->details()->stateOrRegion(),
            'postal_code' => $address->details()->postalCode(),
            'country_code' => $address->details()->countryCode()->value(),
        ];
    }
}
