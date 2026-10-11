<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Authentication;

use Illuminate\Foundation\Http\FormRequest;

/** Validates the address used to request another verification email. */
final class ResendEmailVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:254']];
    }
}
