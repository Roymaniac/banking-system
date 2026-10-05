<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Persistence;

use Audit\Application\Query\AuditTrailQuery;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/** Builds bounded audit views from the three append-only audit tables. */
final readonly class DatabaseAuditTrailQuery implements AuditTrailQuery
{
    public function __construct(private ConnectionInterface $connection) {}

    public function domainEvents(array $filters, int $page, int $perPage): array
    {
        $query = $this->connection->table('audit_log');
        $this->applyPeriod($query, $filters, 'occurred_on');

        foreach (['event_name', 'aggregate_type', 'aggregate_id', 'correlation_id'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        return $this->paginate(
            $query,
            'event_id',
            $page,
            $perPage,
            fn (object $row): array => [
                'event_id' => $row->event_id,
                'event_name' => $row->event_name,
                'aggregate_type' => $row->aggregate_type,
                'aggregate_id' => $row->aggregate_id,
                'aggregate_version' => (int) $row->aggregate_version,
                'correlation_id' => $row->correlation_id,
                'payload' => $this->json($row->payload),
                'occurred_on' => $this->date($row->occurred_on),
                'recorded_at' => $this->date($row->recorded_at),
            ],
        );
    }

    public function activities(array $filters, int $page, int $perPage): array
    {
        $query = $this->connection->table('activity_log');
        $this->applyPeriod($query, $filters, 'occurred_on');

        foreach (['actor_id', 'action', 'response_status'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        return $this->paginate(
            $query,
            'id',
            $page,
            $perPage,
            fn (object $row): array => [
                'id' => $row->id,
                'actor_type' => $row->actor_type,
                'actor_id' => $row->actor_id,
                'action' => $row->action,
                'http_method' => $row->http_method,
                'response_status' => (int) $row->response_status,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'metadata' => $this->json($row->metadata),
                'occurred_on' => $this->date($row->occurred_on),
            ],
        );
    }

    public function securityEvents(array $filters, int $page, int $perPage): array
    {
        $query = $this->connection->table('security_events');
        $this->applyPeriod($query, $filters, 'occurred_on');

        foreach (['type', 'severity', 'subject_id'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        return $this->paginate(
            $query,
            'id',
            $page,
            $perPage,
            fn (object $row): array => [
                'id' => $row->id,
                'type' => $row->type,
                'severity' => $row->severity,
                'subject_id' => $row->subject_id,
                'subject_fingerprint' => $row->subject_fingerprint,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'details' => $this->json($row->details),
                'occurred_on' => $this->date($row->occurred_on),
            ],
        );
    }

    /** @param array{from?: DateTimeImmutable, to?: DateTimeImmutable} $filters */
    private function applyPeriod(Builder $query, array $filters, string $column): void
    {
        if (isset($filters['from'])) {
            $query->where($column, '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->where($column, '<=', $filters['to']);
        }
    }

    /**
     * @param  callable(object): array<string, mixed>  $transform
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    private function paginate(
        Builder $query,
        string $idColumn,
        int $page,
        int $perPage,
        callable $transform,
    ): array {
        $total = (clone $query)->count();
        $items = $query->orderByDesc('occurred_on')
            ->orderByDesc($idColumn)
            ->forPage($page, $perPage)
            ->get()
            ->map($transform)
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function json(string $value): array
    {
        /** @var array<string, mixed> */
        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function date(string $value): string
    {
        return (new DateTimeImmutable($value))->format(DATE_ATOM);
    }
}
