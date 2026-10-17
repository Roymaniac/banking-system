<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operation;

use Illuminate\Foundation\Http\FormRequest;

/** Validates privacy-conscious customer selector searches. */
final class ListOperatorCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return array{search: string|null, page: int, per_page: int} */
    public function filters(): array
    {
        $search = trim($this->string('search')->toString());

        return [
            'search' => $search === '' ? null : $search,
            'page' => $this->integer('page', 1),
            'per_page' => $this->integer('per_page', 10),
        ];
    }
}
