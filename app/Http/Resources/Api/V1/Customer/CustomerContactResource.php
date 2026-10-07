<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use Customer\Domain\Customer\Contact\CustomerContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Defines the JSON shape of one customer contact method.
 *
 * @mixin CustomerContact
 */
final class CustomerContactResource extends JsonResource
{
    /** @return array{id: string, type: string, value: string} */
    public function toArray(Request $request): array
    {
        /** @var CustomerContact $contact */
        $contact = $this->resource;

        return [
            'id' => $contact->id()->value(),
            'type' => $contact->contactPoint()->type()->value,
            'value' => $contact->contactPoint()->value(),
        ];
    }
}
