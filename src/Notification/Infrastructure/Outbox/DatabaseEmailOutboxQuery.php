<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Outbox;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Notification\Application\Outbox\EmailOutboxQuery;

/** Reports delivery health while deliberately never selecting encrypted email data. */
final readonly class DatabaseEmailOutboxQuery implements EmailOutboxQuery
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private ConnectionInterface $connection) {}

    public function search(array $filters, int $page, int $perPage): array
    {
        $query = $this->connection->table('email_outbox')
            ->select(
                'id',
                'attempts',
                'retry_cycles',
                'recorded_at',
                'last_attempted_at',
                'requeued_at',
                'delivered_at',
            );

        if (isset($filters['from'])) {
            $query->where('recorded_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->where('recorded_at', '<=', $filters['to']);
        }
        if (isset($filters['attempts'])) {
            $query->where('attempts', $filters['attempts']);
        }
        if (isset($filters['status'])) {
            $this->applyStatus($query, $filters['status']);
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'status' => $this->status($row),
                'attempts' => (int) $row->attempts,
                'maximum_attempts' => self::MAX_ATTEMPTS,
                'retry_cycles' => (int) $row->retry_cycles,
                'recorded_at' => $this->date($row->recorded_at),
                'last_attempted_at' => $this->nullableDate($row->last_attempted_at),
                'requeued_at' => $this->nullableDate($row->requeued_at),
                'delivered_at' => $this->nullableDate($row->delivered_at),
            ])
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
            // Overall counts help operators see queue health even while a list is filtered.
            'summary' => [
                'pending' => $this->baseQuery()->whereNull('delivered_at')->where('attempts', '<', self::MAX_ATTEMPTS)->count(),
                'delivered' => $this->baseQuery()->whereNotNull('delivered_at')->count(),
                'exhausted' => $this->baseQuery()->whereNull('delivered_at')->where('attempts', '>=', self::MAX_ATTEMPTS)->count(),
            ],
        ];
    }

    private function applyStatus(Builder $query, string $status): void
    {
        if ($status === 'delivered') {
            $query->whereNotNull('delivered_at');

            return;
        }

        $query->whereNull('delivered_at');
        $status === 'pending'
            ? $query->where('attempts', '<', self::MAX_ATTEMPTS)
            : $query->where('attempts', '>=', self::MAX_ATTEMPTS);
    }

    private function status(object $row): string
    {
        if ($row->delivered_at !== null) {
            return 'delivered';
        }

        return (int) $row->attempts >= self::MAX_ATTEMPTS ? 'exhausted' : 'pending';
    }

    private function baseQuery(): Builder
    {
        return $this->connection->table('email_outbox');
    }

    private function date(string $value): string
    {
        return (new DateTimeImmutable($value))->format(DATE_ATOM);
    }

    private function nullableDate(?string $value): ?string
    {
        return $value === null ? null : $this->date($value);
    }
}
