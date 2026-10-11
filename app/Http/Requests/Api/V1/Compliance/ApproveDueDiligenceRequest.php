<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Compliance;

use Compliance\Domain\DueDiligence\RiskLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the risk level recorded when compliance approves a customer. */
final class ApproveDueDiligenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['risk_level' => ['required', 'string', Rule::enum(RiskLevel::class)]];
    }
}
