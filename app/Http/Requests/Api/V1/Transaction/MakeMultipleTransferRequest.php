<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Transaction;

use Illuminate\Foundation\Http\FormRequest;

/** Validates one bounded, all-or-nothing batch of recipient payments. */
final class MakeMultipleTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\\/-]{0,99}$/'],
            'recipients' => ['required', 'array', 'min:1', 'max:20'],
            'recipients.*.account_number' => [
                'required',
                'string',
                'regex:/^[1-9][0-9]{9}$/',
                'distinct',
            ],
            'recipients.*.minor_units' => ['required', 'integer', 'min:1'],
        ];
    }
}
