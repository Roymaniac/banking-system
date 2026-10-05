<?php

declare(strict_types=1);

namespace Audit\Application\Query;

use DateTimeImmutable;

/** Reads investigation views without exposing mutation methods for audit history. */
interface AuditTrailQuery
{
    /**
     * @param  array{from?: DateTimeImmutable, to?: DateTimeImmutable, event_name?: string, aggregate_type?: string, aggregate_id?: string, correlation_id?: string}  $filters
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function domainEvents(array $filters, int $page, int $perPage): array;

    /**
     * @param  array{from?: DateTimeImmutable, to?: DateTimeImmutable, actor_id?: string, action?: string, response_status?: int}  $filters
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function activities(array $filters, int $page, int $perPage): array;

    /**
     * @param  array{from?: DateTimeImmutable, to?: DateTimeImmutable, type?: string, severity?: string, subject_id?: string}  $filters
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function securityEvents(array $filters, int $page, int $perPage): array;
}
