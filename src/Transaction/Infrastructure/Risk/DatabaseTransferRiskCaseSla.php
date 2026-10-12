<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Risk;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Risk\TransferRiskCase;
use Transaction\Application\Risk\TransferRiskCaseNotifier;
use Transaction\Application\Risk\TransferRiskCaseSla;

/** Sends one reminder before the deadline and one escalation after it. */
final readonly class DatabaseTransferRiskCaseSla implements TransferRiskCaseSla
{
    private const BATCH_SIZE = 50;

    public function __construct(
        private ConnectionInterface $connection,
        private TransferRiskCaseNotifier $notifier,
        private Clock $clock,
    ) {}

    public function processDue(): array
    {
        return $this->connection->transaction(function (): array {
            $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
            $reminderMinutes = max(1, (int) config('security.transfer_risk.reminder_minutes_before_due'));
            $reminderCutoff = $now->add(new DateInterval("PT{$reminderMinutes}M"));
            $reminded = $this->notifyDue(
                'reminder',
                fn ($query) => $query->whereNull('reminded_at')
                    ->whereNull('escalated_at')
                    ->where('due_at', '>', $now)
                    ->where('due_at', '<=', $reminderCutoff),
                $now,
            );
            $escalated = $this->notifyDue(
                'escalated',
                fn ($query) => $query->whereNull('escalated_at')
                    ->where('due_at', '<=', $now),
                $now,
            );

            return ['reminded' => $reminded, 'escalated' => $escalated];
        });
    }

    private function notifyDue(string $kind, callable $scope, DateTimeImmutable $now): int
    {
        $query = $this->connection->table('transfer_risk_cases')
            ->whereIn('status', ['pending', 'in_review'])
            ->whereNotNull('due_at');
        $records = $scope($query)
            ->orderBy('due_at')
            ->limit(self::BATCH_SIZE)
            ->lockForUpdate()
            ->get();
        $processed = 0;

        foreach ($records as $record) {
            $case = $this->hydrate($record);
            $recipients = $kind === 'reminder'
                ? $this->notifier->reminder($case)
                : $this->notifier->escalated($case);

            if ($recipients === 0) {
                // Leave the checkpoint empty so a later scheduler run retries
                // after reviewer access or email verification is restored.
                continue;
            }

            $this->connection->table('transfer_risk_cases')
                ->where('id', $record->id)
                ->whereNull($kind === 'reminder' ? 'reminded_at' : 'escalated_at')
                ->update([$kind === 'reminder' ? 'reminded_at' : 'escalated_at' => $now]);
            $processed++;
        }

        return $processed;
    }

    private function hydrate(object $record): TransferRiskCase
    {
        $timezone = new DateTimeZone('UTC');
        $number = $this->connection->table('accounts')->where('id', $record->account_id)->value('number');

        return new TransferRiskCase(
            new Uuid((string) $record->id),
            $number === null ? '----' : substr((string) $number, -4),
            (string) $record->rule,
            (string) $record->status,
            (int) $record->window_minutes,
            (int) $record->maximum_transfers,
            new DateTimeImmutable((string) $record->detected_at, $timezone),
            new DateTimeImmutable((string) $record->due_at, $timezone),
            $record->reminded_at === null ? null : new DateTimeImmutable((string) $record->reminded_at, $timezone),
            $record->escalated_at === null ? null : new DateTimeImmutable((string) $record->escalated_at, $timezone),
            $record->claimed_by === null ? null : new Uuid((string) $record->claimed_by),
            $record->claimed_at === null ? null : new DateTimeImmutable((string) $record->claimed_at, $timezone),
            $record->resolved_by === null ? null : new Uuid((string) $record->resolved_by),
            $record->resolution === null ? null : (string) $record->resolution,
            $record->resolution_note === null ? null : (string) $record->resolution_note,
            $record->resolved_at === null ? null : new DateTimeImmutable((string) $record->resolved_at, $timezone),
        );
    }
}
