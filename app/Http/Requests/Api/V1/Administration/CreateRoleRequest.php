<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class CreateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Role names are stable identifiers used by the application, not display text.
            'name' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_]{2,49}$/'],
            'label' => ['required', 'string', 'min:3', 'max:100'],
        ];
    }
}
