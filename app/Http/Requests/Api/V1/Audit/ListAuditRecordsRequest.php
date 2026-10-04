<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Audit;

use Audit\Domain\Security\SecurityEventType;
use Audit\Domain\Security\SecuritySeverity;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the shared investigation filters accepted by audit endpoints. */
final class ListAuditRecordsRequest extends FormRequest
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
            // Audit data is sensitive and potentially huge, so bulk dumps are not allowed.
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'event_name' => ['sometimes', 'string', 'max:255'],
            'aggregate_type' => ['sometimes', 'string', 'max:255'],
            'aggregate_id' => ['sometimes', 'string', 'max:255'],
            'correlation_id' => ['sometimes', 'uuid'],
            'actor_id' => ['sometimes', 'string', 'max:255'],
            'action' => ['sometimes', 'string', 'max:255'],
            'response_status' => ['sometimes', 'integer', 'min:100', 'max:599'],
            'type' => ['sometimes', Rule::enum(SecurityEventType::class)],
            'severity' => ['sometimes', Rule::enum(SecuritySeverity::class)],
            'subject_id' => ['sometimes', 'string', 'max:255'],
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

    /** @return array<string, DateTimeImmutable|int|string> */
    public function filters(): array
    {
        $filters = [];
        $timezone = new DateTimeZone('UTC');

        if ($this->filled('from')) {
            $filters['from'] = new DateTimeImmutable($this->string('from')->toString() . ' 00:00:00', $timezone);
        }
        if ($this->filled('to')) {
            $filters['to'] = new DateTimeImmutable($this->string('to')->toString() . ' 23:59:59.999999', $timezone);
        }

        foreach (
            [
                'event_name',
                'aggregate_type',
                'aggregate_id',
                'correlation_id',
                'actor_id',
                'action',
                'type',
                'severity',
                'subject_id',
            ] as $name
        ) {
            if ($this->filled($name)) {
                $filters[$name] = $this->string($name)->toString();
            }
        }

        if ($this->filled('response_status')) {
            $filters['response_status'] = $this->integer('response_status');
        }

        return $filters;
    }
}
