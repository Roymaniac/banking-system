<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Authentication;

use Illuminate\Foundation\Http\FormRequest;

/** Accepts only the fixed-size random token sent in a verification email. */
final class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i']];
    }
}
