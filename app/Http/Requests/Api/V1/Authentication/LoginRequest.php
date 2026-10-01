<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Authentication;

use Illuminate\Foundation\Http\FormRequest;

/** Validates login input before plain credentials reach the use case. */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
