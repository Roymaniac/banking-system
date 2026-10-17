<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates bounded account searches used by trusted operator selectors. */
final class ListOperatorAccountsRequest extends FormRequest
{
    public const PURPOSES = [
        'provision',
        'freeze',
        'unfreeze',
        'close',
        'deposit',
        'withdrawal',
        'transaction-report',
        'audit',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in(self::PURPOSES)],
            'search' => ['sometimes', 'string', 'max:100'],
            'customer_id' => ['sometimes', 'uuid'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'active', 'frozen', 'closed'])],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'type' => ['sometimes', 'string', Rule::in(['savings', 'current'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return [
            'purpose' => $this->string('purpose')->toString(),
            'search' => $this->term(),
            'customer_id' => $this->filled('customer_id') ? $this->string('customer_id')->toString() : null,
            'status' => $this->filled('status') ? $this->string('status')->toString() : null,
            'currency' => $this->filled('currency') ? strtoupper($this->string('currency')->toString()) : null,
            'type' => $this->filled('type') ? $this->string('type')->toString() : null,
            'page' => $this->integer('page', 1),
            'per_page' => $this->integer('per_page', 10),
        ];
    }

    public function purpose(): string
    {
        return $this->string('purpose')->toString();
    }

    private function term(): ?string
    {
        $term = trim($this->string('search')->toString());

        return $term === '' ? null : $term;
    }
}
