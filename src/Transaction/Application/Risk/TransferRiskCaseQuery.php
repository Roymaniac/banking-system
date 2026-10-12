<?php

declare(strict_types=1);

namespace Transaction\Application\Risk;

use DateTimeImmutable;

interface TransferRiskCaseQuery
{
    /**
     * @param  array{from?: DateTimeImmutable, to?: DateTimeImmutable, status?: string, rule?: string}  $filters
     * @return array{items: list<TransferRiskCase>, pagination: array<string, int>}
     */
    public function list(array $filters, int $page, int $perPage): array;
}
