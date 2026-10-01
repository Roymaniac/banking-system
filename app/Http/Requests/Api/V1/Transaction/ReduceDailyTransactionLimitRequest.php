<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Transaction;

use Illuminate\Foundation\Http\FormRequest;

/** Validates the lower daily amount selected by a customer. */
final class ReduceDailyTransactionLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'maximum_minor_units' => ['required', 'integer', 'min:1'],
        ];
    }
}
