<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Transaction;

use Illuminate\Foundation\Http\FormRequest;

/** Validates a customer's immediate account-to-account transfer request. */
final class MakeTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'recipient_account_number' => ['required', 'string', 'regex:/^[1-9][0-9]{9}$/'],
            'minor_units' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\\/-]{0,99}$/'],
        ];
    }
}
