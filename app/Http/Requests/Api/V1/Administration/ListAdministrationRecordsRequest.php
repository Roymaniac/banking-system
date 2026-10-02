<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration;

use Illuminate\Foundation\Http\FormRequest;

/** Validates shared filters for bounded administration lists. */
final class ListAdministrationRecordsRequest extends FormRequest
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
            'search' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ];
    }

    public function pageNumber(): int
    {
        return $this->integer('page', 1);
    }

    public function pageSize(): int
    {
        return $this->integer('per_page', 25);
    }

    public function searchTerm(): ?string
    {
        $search = trim($this->string('search')->toString());

        return $search === '' ? null : $search;
    }

    public function requestedStatus(): ?string
    {
        $status = $this->string('status')->toString();

        return $status === '' ? null : $status;
    }
}
