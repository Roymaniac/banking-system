<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use Closure;
use Customer\Domain\Customer\Contact\ValueObject\ContactType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates an email address or international phone number. */
final class CustomerContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ContactType::class)],
            'value' => [
                'required',
                'string',
                'max:254',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $valid = match ($this->input('type')) {
                        ContactType::Email->value => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                        ContactType::Phone->value => preg_match('/^\+[1-9][0-9]{7,14}$/', preg_replace('/[\s()-]+/', '', $value) ?? '') === 1,
                        default => true,
                    };

                    if (! $valid) {
                        $fail('The contact value does not match the selected contact type.');
                    }
                },
            ],
        ];
    }
}
