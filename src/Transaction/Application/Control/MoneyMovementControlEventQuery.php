<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

use DateTimeImmutable;

interface MoneyMovementControlEventQuery
{
    /**
     * @param  array{from?: DateTimeImmutable, to?: DateTimeImmutable, action?: string, source?: string, actor_user_id?: string}  $filters
     * @return array{items: list<MoneyMovementControlEventView>, pagination: array<string, int>}
     */
    public function list(array $filters, int $page, int $perPage): array;
}
