<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Compliance;

use Illuminate\Foundation\Http\FormRequest;

/** Requires a useful explanation when compliance rejects submitted evidence. */
final class RejectDueDiligenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:12', 'max:500']];
    }
}
