<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Transaction;

use Illuminate\Foundation\Http\FormRequest;

/** Validates an account number before showing a privacy-safe recipient preview. */
final class ConfirmTransferRecipientRequest extends FormRequest
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
        ];
    }
}
