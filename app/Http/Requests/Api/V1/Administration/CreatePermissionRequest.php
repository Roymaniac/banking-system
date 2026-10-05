<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class CreatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Dot notation describes the protected area and action, such as accounts.view.
            'name' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_-]*\.[A-Za-z][A-Za-z0-9_-]*$/'],
            'label' => ['required', 'string', 'min:3', 'max:150'],
        ];
    }
}
