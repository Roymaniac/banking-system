<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Control;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Control\MoneyMovementResumeRequest;
use Transaction\Application\Control\MoneyMovementResumeRequestQuery;

/** Provides a bounded approval queue and historical review view. */
final readonly class DatabaseMoneyMovementResumeRequestQuery implements MoneyMovementResumeRequestQuery
{
    public function __construct(private ConnectionInterface $connection) {}

    public function list(array $filters, int $page, int $perPage): array
    {
        $query = $this->connection->table('money_movement_resume_requests');

        if (isset($filters['from'])) {
            $query->where('requested_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->where('requested_at', '<=', $filters['to']);
        }

        foreach (['status', 'requested_by', 'approved_by', 'incident_reference'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (object $record): MoneyMovementResumeRequest => $this->hydrate($record))
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

    private function hydrate(object $record): MoneyMovementResumeRequest
    {
        $timezone = new DateTimeZone('UTC');

        return new MoneyMovementResumeRequest(
            new Uuid((string) $record->id),
            new Uuid((string) $record->requested_by),
            (string) $record->reason,
            $record->incident_reference === null ? null : (string) $record->incident_reference,
            $record->evidence_summary === null ? null : (string) $record->evidence_summary,
            $record->control_revision === null ? null : (int) $record->control_revision,
            (string) $record->status,
            new DateTimeImmutable((string) $record->requested_at, $timezone),
            new DateTimeImmutable((string) $record->expires_at, $timezone),
            $record->approved_by === null ? null : new Uuid((string) $record->approved_by),
            $record->approved_at === null ? null : new DateTimeImmutable((string) $record->approved_at, $timezone),
            $record->closed_by === null ? null : new Uuid((string) $record->closed_by),
            $record->closure_reason === null ? null : (string) $record->closure_reason,
            $record->closed_at === null ? null : new DateTimeImmutable((string) $record->closed_at, $timezone),
        );
    }
}
