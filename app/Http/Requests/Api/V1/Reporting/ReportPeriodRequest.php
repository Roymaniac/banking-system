<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Reporting;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

/** Validates a bounded page within an inclusive UTC reporting period. */
final class ReportPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            // Reports may be large, but every response must remain bounded.
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function from(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $this->string('from')->toString().' 00:00:00',
            new DateTimeZone('UTC'),
        );
    }

    public function to(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $this->string('to')->toString().' 23:59:59.999999',
            new DateTimeZone('UTC'),
        );
    }

    public function pageNumber(): int
    {
        return $this->integer('page', 1);
    }

    public function pageSize(): int
    {
        return $this->integer('per_page', 100);
    }
}
