<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class HireStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
            'employee_number' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{3,20}$/'],
            'department_id' => ['required', 'uuid'],
            'job_title' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }
}
