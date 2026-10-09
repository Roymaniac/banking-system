<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operation;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates bounded filters for the independent-approval queue. */
final class ListMoneyMovementResumeRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'cancelled', 'expired', 'superseded'])],
            'requested_by' => ['sometimes', 'uuid'],
            'approved_by' => ['sometimes', 'uuid'],
            'incident_reference' => [
                'sometimes',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
            ],
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

    /** @return array<string, DateTimeImmutable|string> */
    public function filters(): array
    {
        $filters = [];
        $timezone = new DateTimeZone('UTC');

        if ($this->filled('from')) {
            $filters['from'] = new DateTimeImmutable($this->string('from')->toString().' 00:00:00', $timezone);
        }
        if ($this->filled('to')) {
            $filters['to'] = new DateTimeImmutable($this->string('to')->toString().' 23:59:59.999999', $timezone);
        }

        foreach (['status', 'requested_by', 'approved_by', 'incident_reference'] as $name) {
            if ($this->filled($name)) {
                $value = $this->string($name)->toString();
                $filters[$name] = $name === 'incident_reference' ? strtoupper($value) : $value;
            }
        }

        return $filters;
    }
}
