<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Compliance;

use Compliance\Domain\DueDiligence\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates identity evidence without copying it into logs or URLs. */
final class SubmitDueDiligenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', Rule::enum(DocumentType::class)],
            'issuing_country' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'document_number' => ['required', 'string', 'min:6', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }
}
