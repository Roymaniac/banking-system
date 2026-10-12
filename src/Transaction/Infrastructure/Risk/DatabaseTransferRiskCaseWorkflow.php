<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Risk;

use DateTimeImmutable;
use DateTimeZone;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\ConnectionInterface;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Risk\TransferRiskCase;
use Transaction\Application\Risk\TransferRiskCaseQuery;
use Transaction\Application\Risk\TransferRiskCaseReview;
use Transaction\Domain\Risk\Exception\InvalidTransferRiskCaseTransition;
use Transaction\Domain\Risk\Exception\TransferRiskCaseNotFound;
use Transaction\Domain\Risk\TransferRiskResolution;

/** Provides the bounded queue and concurrency-safe reviewer workflow. */
final readonly class DatabaseTransferRiskCaseWorkflow implements TransferRiskCaseQuery, TransferRiskCaseReview
{
    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function list(array $filters, int $page, int $perPage): array
    {
        $query = $this->connection->table('transfer_risk_cases as risk_cases')
            ->join('accounts', 'accounts.id', '=', 'risk_cases.account_id')
            ->select(['risk_cases.*', 'accounts.number as account_number']);

        if (isset($filters['from'])) {
            $query->where('risk_cases.detected_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->where('risk_cases.detected_at', '<=', $filters['to']);
        }
        foreach (['status', 'rule'] as $column) {
            if (isset($filters[$column])) {
                $query->where('risk_cases.'.$column, $filters[$column]);
            }
        }

        $total = (clone $query)->count();
        $records = $query->orderByDesc('risk_cases.detected_at')
            ->orderByDesc('risk_cases.id')
            ->forPage($page, $perPage)
            ->get();

        return [
            'items' => $records->map(fn (object $record): TransferRiskCase => $this->hydrate($record))
                ->values()
                ->all(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function claim(Uuid $caseId, UserId $reviewerId): TransferRiskCase
    {
        return $this->connection->transaction(function () use ($caseId, $reviewerId): TransferRiskCase {
            $record = $this->locked($caseId);

            if ($record->status === 'resolved') {
                throw InvalidTransferRiskCaseTransition::resolved();
            }
            if ($record->status === 'in_review') {
                if ((string) $record->claimed_by !== $reviewerId->value()) {
                    throw InvalidTransferRiskCaseTransition::alreadyClaimed();
                }

                return $this->hydrate($record);
            }

            $claimedAt = $this->clock->now();
            $updated = $this->connection->table('transfer_risk_cases')
                ->where('id', $caseId->value())
                ->where('version', (int) $record->version)
                ->update([
                    'status' => 'in_review',
                    'claimed_by' => $reviewerId->value(),
                    'claimed_at' => $claimedAt,
                    'version' => (int) $record->version + 1,
                ]);

            if ($updated !== 1) {
                throw InvalidTransferRiskCaseTransition::changed();
            }

            return $this->hydrate((object) [
                ...get_object_vars($record),
                'status' => 'in_review',
                'claimed_by' => $reviewerId->value(),
                'claimed_at' => $claimedAt,
                'version' => (int) $record->version + 1,
            ]);
        });
    }

    public function resolve(
        Uuid $caseId,
        UserId $reviewerId,
        TransferRiskResolution $resolution,
        string $note,
    ): TransferRiskCase {
        return $this->connection->transaction(function () use (
            $caseId,
            $reviewerId,
            $resolution,
            $note,
        ): TransferRiskCase {
            $record = $this->locked($caseId);

            if ($record->status === 'resolved') {
                throw InvalidTransferRiskCaseTransition::resolved();
            }
            if ($record->status !== 'in_review' || (string) $record->claimed_by !== $reviewerId->value()) {
                throw InvalidTransferRiskCaseTransition::notClaimedByReviewer();
            }

            $resolvedAt = $this->clock->now();
            $normalizedNote = trim($note);
            $updated = $this->connection->table('transfer_risk_cases')
                ->where('id', $caseId->value())
                ->where('version', (int) $record->version)
                ->update([
                    'status' => 'resolved',
                    'resolved_by' => $reviewerId->value(),
                    'resolution' => $resolution->value,
                    'resolution_note' => $normalizedNote,
                    'resolved_at' => $resolvedAt,
                    'open_key' => null,
                    'version' => (int) $record->version + 1,
                ]);

            if ($updated !== 1) {
                throw InvalidTransferRiskCaseTransition::changed();
            }

            return $this->hydrate((object) [
                ...get_object_vars($record),
                'status' => 'resolved',
                'resolved_by' => $reviewerId->value(),
                'resolution' => $resolution->value,
                'resolution_note' => $normalizedNote,
                'resolved_at' => $resolvedAt,
                'open_key' => null,
                'version' => (int) $record->version + 1,
            ]);
        });
    }

    private function locked(Uuid $caseId): object
    {
        return $this->connection->table('transfer_risk_cases')
            ->where('id', $caseId->value())
            ->lockForUpdate()
            ->first() ?? throw TransferRiskCaseNotFound::create();
    }

    private function hydrate(object $record): TransferRiskCase
    {
        $number = property_exists($record, 'account_number')
            ? $record->account_number
            : $this->connection->table('accounts')->where('id', $record->account_id)->value('number');
        $timezone = new DateTimeZone('UTC');

        return new TransferRiskCase(
            new Uuid((string) $record->id),
            $number === null ? '----' : substr((string) $number, -4),
            (string) $record->rule,
            (string) $record->status,
            (int) $record->window_minutes,
            (int) $record->maximum_transfers,
            $this->dateTime($record->detected_at, $timezone),
            $record->claimed_by === null ? null : new Uuid((string) $record->claimed_by),
            $this->nullableDateTime($record->claimed_at, $timezone),
            $record->resolved_by === null ? null : new Uuid((string) $record->resolved_by),
            $record->resolution === null ? null : (string) $record->resolution,
            $record->resolution_note === null ? null : (string) $record->resolution_note,
            $this->nullableDateTime($record->resolved_at, $timezone),
        );
    }

    private function dateTime(mixed $value, DateTimeZone $timezone): DateTimeImmutable
    {
        return $value instanceof DateTimeImmutable
            ? $value->setTimezone($timezone)
            : new DateTimeImmutable((string) $value, $timezone);
    }

    private function nullableDateTime(mixed $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        return $value === null ? null : $this->dateTime($value, $timezone);
    }
}
