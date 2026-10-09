<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operation;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates bounded filters for reviewing safety-switch activity. */
final class ListMoneyMovementControlEventsRequest extends FormRequest
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
            'action' => ['sometimes', Rule::in(['suspended', 'resumed'])],
            'source' => ['sometimes', 'string', 'max:50'],
            'actor_user_id' => ['sometimes', 'uuid'],
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

        foreach (['action', 'source', 'actor_user_id'] as $name) {
            if ($this->filled($name)) {
                $filters[$name] = $this->string($name)->toString();
            }
        }

        return $filters;
    }
}
