<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Notification;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

/** Validates bounded operational filters for email delivery metadata. */
final class ListEmailOutboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['sometimes', 'string', 'in:pending,delivered,exhausted'],
            'attempts' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
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

    /** @return array{from?: DateTimeImmutable, to?: DateTimeImmutable, status?: string, attempts?: int} */
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
        if ($this->filled('status')) {
            $filters['status'] = $this->string('status')->toString();
        }
        if ($this->filled('attempts')) {
            $filters['attempts'] = $this->integer('attempts');
        }

        return $filters;
    }
}
