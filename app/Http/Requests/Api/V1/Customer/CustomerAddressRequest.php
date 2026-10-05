<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use Customer\Domain\Customer\Address\ValueObject\AddressType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the complete address required for both creation and replacement. */
final class CustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AddressType::class)],
            'line_one' => ['required', 'string', 'max:150'],
            'line_two' => ['nullable', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:150'],
            'state_or_region' => ['required', 'string', 'max:150'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2', 'alpha:ascii'],
        ];
    }
}
