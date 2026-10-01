<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use Customer\Domain\Customer\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Converts a Customer domain object into its public API representation. */
final class CustomerProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Customer $customer */
        $customer = $this->resource;

        return [
            'id' => $customer->id()->value(),
            'first_name' => $customer->name()->firstName(),
            'middle_name' => $customer->name()->middleName(),
            'last_name' => $customer->name()->lastName(),
            'date_of_birth' => $customer->dateOfBirth()->value(),
            'registered_at' => $customer->registeredAt()->format(DATE_ATOM),
            'addresses' => CustomerAddressResource::collection($customer->addresses()),
            'contacts' => CustomerContactResource::collection($customer->contacts()),
        ];
    }
}
