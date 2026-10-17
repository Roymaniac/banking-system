<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates bounded searches for original journal entries to reverse. */
final class ListReversalEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in(['reversal'])],
            'search' => ['sometimes', 'string', 'max:100'],
            'account_id' => ['sometimes', 'uuid'],
            'customer_id' => ['sometimes', 'uuid'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $search = trim($this->string('search')->toString());

        return [
            'search' => $search === '' ? null : $search,
            'account_id' => $this->filled('account_id') ? $this->string('account_id')->toString() : null,
            'customer_id' => $this->filled('customer_id') ? $this->string('customer_id')->toString() : null,
            'from' => $this->filled('from') ? $this->string('from')->toString() : null,
            'to' => $this->filled('to') ? $this->string('to')->toString() : null,
            'page' => $this->integer('page', 1),
            'per_page' => $this->integer('per_page', 10),
        ];
    }
}
