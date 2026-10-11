<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Compliance;

use Illuminate\Foundation\Http\FormRequest;

/** Keeps the compliance review queue predictably bounded. */
final class ListDueDiligenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
