<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Control;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Control\MoneyMovementResumeNotifier;
use Transaction\Application\Control\MoneyMovementResumeRequest;
use Transaction\Application\Control\MoneyMovementResumeRequestExpiry;

/** Makes elapsed approval windows explicit instead of leaving stale requests pending. */
final readonly class DatabaseMoneyMovementResumeRequestExpiry implements MoneyMovementResumeRequestExpiry
{
    public function __construct(
        private ConnectionInterface $connection,
        private MoneyMovementResumeNotifier $notifier,
        private Clock $clock,
    ) {}

    public function expire(): int
    {
        return $this->connection->transaction(function (): int {
            $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
            $control = $this->connection->table('money_movement_controls')
                ->where('name', 'global')
                ->lockForUpdate()
                ->first();
            $expiredRecords = $this->connection->table('money_movement_resume_requests')
                ->where('status', 'pending')
                ->where('expires_at', '<=', $now)
                ->lockForUpdate()
                ->get();
            $changed = $this->closeRecords(
                $expiredRecords,
                'expired',
                'The approval window elapsed before independent review.',
                $now,
            );

            if ($control === null) {
                return $changed;
            }

            $supersededRecords = $this->connection->table('money_movement_resume_requests')
                ->where('status', 'pending')
                ->where(function ($query) use ($control): void {
                    $query->whereNull('control_revision')
                        ->orWhere('control_revision', '<>', (int) $control->revision);
                })
                ->lockForUpdate()
                ->get();

            return $changed + $this->closeRecords(
                $supersededRecords,
                'superseded',
                'A newer suspension replaced this approval request.',
                $now,
            );
        });
    }

    /**
     * @param  iterable<int, object>  $records
     */
    private function closeRecords(
        iterable $records,
        string $status,
        string $closureReason,
        DateTimeImmutable $now,
    ): int {
        $changed = 0;

        foreach ($records as $record) {
            $request = $this->hydrate($record);
            $updated = $this->connection->table('money_movement_resume_requests')
                ->where('id', $request->id->value())
                ->where('status', 'pending')
                ->update([
                    'status' => $status,
                    'closure_reason' => $closureReason,
                    'closed_at' => $now,
                ]);

            if ($updated === 0) {
                continue;
            }

            $closed = new MoneyMovementResumeRequest(
                $request->id,
                $request->requestedBy,
                $request->reason,
                $request->incidentReference,
                $request->evidenceSummary,
                $request->controlRevision,
                $status,
                $request->requestedAt,
                $request->expiresAt,
                closedBy: null,
                closureReason: $closureReason,
                closedAt: $now,
                remindedAt: $request->remindedAt,
            );
            $this->notifier->outcome($closed);
            $changed++;
        }

        return $changed;
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
            $record->reminded_at === null ? null : new DateTimeImmutable((string) $record->reminded_at, $timezone),
        );
    }
}
