<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Account\Domain\Account\ValueObject\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the banking product and currency requested by a customer. */
final class CreateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AccountType::class)],
            'currency' => ['required', 'string', 'size:3', 'alpha:ascii'],
        ];
    }
}
