<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Control;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Control\MoneyMovementResumeNotifier;
use Transaction\Application\Control\MoneyMovementResumeReminder;
use Transaction\Application\Control\MoneyMovementResumeRequest;

/** Sends one durable reminder when a current approval has ten minutes left. */
final readonly class DatabaseMoneyMovementResumeReminder implements MoneyMovementResumeReminder
{
    private const REMINDER_WINDOW_MINUTES = 10;

    private const BATCH_SIZE = 50;

    public function __construct(
        private ConnectionInterface $connection,
        private MoneyMovementResumeNotifier $notifier,
        private Clock $clock,
    ) {}

    public function sendDue(): int
    {
        return $this->connection->transaction(function (): int {
            $control = $this->connection->table('money_movement_controls')
                ->where('name', 'global')
                ->first();

            if ($control === null || filter_var($control->enabled, FILTER_VALIDATE_BOOL)) {
                return 0;
            }

            $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
            $cutoff = $now->add(new DateInterval('PT'.self::REMINDER_WINDOW_MINUTES.'M'));
            $records = $this->connection->table('money_movement_resume_requests')
                ->where('status', 'pending')
                ->whereNull('reminded_at')
                ->where('control_revision', (int) $control->revision)
                ->where('expires_at', '>', $now)
                ->where('expires_at', '<=', $cutoff)
                ->orderBy('expires_at')
                ->limit(self::BATCH_SIZE)
                ->lockForUpdate()
                ->get();

            $reminded = 0;

            foreach ($records as $record) {
                $request = $this->hydrate($record);
                if ($this->notifier->expiringSoon($request) === 0) {
                    // Keep reminded_at empty so the scheduler retries after
                    // reviewer access or verification is restored.
                    continue;
                }
                $this->connection->table('money_movement_resume_requests')
                    ->where('id', $request->id->value())
                    ->whereNull('reminded_at')
                    ->update(['reminded_at' => $now]);
                $reminded++;
            }

            return $reminded;
        });
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
