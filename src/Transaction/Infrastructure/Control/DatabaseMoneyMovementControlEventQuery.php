<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Control;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Transaction\Application\Control\MoneyMovementControlEventQuery;
use Transaction\Application\Control\MoneyMovementControlEventView;

/** Reads a bounded operational history without exposing mutation methods. */
final readonly class DatabaseMoneyMovementControlEventQuery implements MoneyMovementControlEventQuery
{
    public function __construct(private ConnectionInterface $connection) {}

    public function list(array $filters, int $page, int $perPage): array
    {
        $query = $this->connection->table('money_movement_control_events');

        if (isset($filters['from'])) {
            $query->where('occurred_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->where('occurred_at', '<=', $filters['to']);
        }

        foreach (['action', 'source', 'actor_user_id'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (object $record): MoneyMovementControlEventView => new MoneyMovementControlEventView(
                (int) $record->id,
                (string) $record->action,
                (string) $record->reason,
                (string) $record->source,
                $record->actor_user_id === null ? null : (string) $record->actor_user_id,
                new DateTimeImmutable((string) $record->occurred_at),
            ))
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
}
