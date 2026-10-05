<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operation;

use Illuminate\Foundation\Http\FormRequest;

/** Validates an amount and idempotency reference from a trusted operator. */
final class MoneyMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'minor_units' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\\/-]{0,99}$/'],
        ];
    }
}
