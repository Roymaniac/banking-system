<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operation;

use Illuminate\Foundation\Http\FormRequest;

/** Requires enough incident evidence for an independent resumption decision. */
final class CreateMoneyMovementResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'incident_reference' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
            ],
            'evidence_summary' => ['required', 'string', 'min:30', 'max:2000'],
        ];
    }
}
