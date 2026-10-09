<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

use DateTimeImmutable;

interface MoneyMovementResumeRequestQuery
{
    /**
     * @param  array{from?: DateTimeImmutable, to?: DateTimeImmutable, status?: string, requested_by?: string, approved_by?: string}  $filters
     * @return array{items: list<MoneyMovementResumeRequest>, pagination: array<string, int>}
     */
    public function list(array $filters, int $page, int $perPage): array;
}
