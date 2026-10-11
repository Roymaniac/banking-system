<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Authentication;

use Illuminate\Foundation\Http\FormRequest;

/** Validates the old password and the confirmed replacement password. */
final class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:1024'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
