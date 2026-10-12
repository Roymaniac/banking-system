<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Authentication;

use Illuminate\Foundation\Http\FormRequest;

final class CompleteMultiFactorLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/i'],
            'code' => ['required', 'string', 'regex:/^(?:\d{6}|[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4})$/'],
        ];
    }
}
