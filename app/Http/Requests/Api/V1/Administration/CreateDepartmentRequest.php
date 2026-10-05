<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class CreateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_]{1,19}$/'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }
}
