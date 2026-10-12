<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Transaction\Domain\Risk\TransferRiskResolution;

/** Accepts only controlled decisions with a useful investigation note. */
final class ResolveTransferRiskCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::enum(TransferRiskResolution::class)],
            'note' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
