<?php

declare(strict_types=1);

namespace Notification\Application\Outbox;

use DateTimeImmutable;

/** Reads safe delivery metadata without decrypting recipients or message contents. */
interface EmailOutboxQuery
{
    /**
     * @param  array{from?: DateTimeImmutable, to?: DateTimeImmutable, status?: string, attempts?: int}  $filters
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, summary: array{pending: int, delivered: int, exhausted: int}}
     */
    public function search(array $filters, int $page, int $perPage): array;
}
