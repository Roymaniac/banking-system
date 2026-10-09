<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Control;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;
use Transaction\Application\Control\Exception\InvalidMoneyMovementResume;
use Transaction\Application\Control\MoneyMovementControl;
use Transaction\Application\Control\MoneyMovementResumeApproval;
use Transaction\Application\Control\MoneyMovementResumeRequest;

/** Requires two distinct authenticated operators before restoring financial writes. */
final readonly class DatabaseMoneyMovementResumeApproval implements MoneyMovementResumeApproval
{
    private const APPROVAL_WINDOW_MINUTES = 30;

    public function __construct(
        private ConnectionInterface $connection,
        private MoneyMovementControl $control,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
    ) {}

    public function request(string $reason, Uuid $requestedBy): MoneyMovementResumeRequest
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('The operational reason must contain between 10 and 255 characters.');
        }

        return $this->connection->transaction(function () use ($reason, $requestedBy): MoneyMovementResumeRequest {
            $this->lockSuspendedControl();
            $now = $this->utcNow();
            $pending = $this->connection->table('money_movement_resume_requests')
                ->where('status', 'pending')
                ->where('expires_at', '>', $now)
                ->lockForUpdate()
                ->exists();

            if ($pending) {
                throw InvalidMoneyMovementResume::pendingRequestExists();
            }

            $request = new MoneyMovementResumeRequest(
                $this->uuidGenerator->generate(),
                $requestedBy,
                $reason,
                'pending',
                $now,
                $now->add(new DateInterval('PT'.self::APPROVAL_WINDOW_MINUTES.'M')),
            );

            $this->connection->table('money_movement_resume_requests')->insert([
                'id' => $request->id->value(),
                'requested_by' => $request->requestedBy->value(),
                'reason' => $request->reason,
                'status' => $request->status,
                'requested_at' => $request->requestedAt,
                'expires_at' => $request->expiresAt,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            return $request;
        });
    }

    public function approve(Uuid $requestId, Uuid $approvedBy): MoneyMovementResumeRequest
    {
        return $this->connection->transaction(function () use ($requestId, $approvedBy): MoneyMovementResumeRequest {
            $this->lockSuspendedControl();
            $record = $this->connection->table('money_movement_resume_requests')
                ->where('id', $requestId->value())
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw InvalidMoneyMovementResume::requestNotFound();
            }

            $request = $this->hydrate($record);
            $now = $this->utcNow();

            if ($request->requestedBy->value() === $approvedBy->value()) {
                throw InvalidMoneyMovementResume::sameOperator();
            }
            if ($request->expiresAt <= $now) {
                throw InvalidMoneyMovementResume::expired();
            }

            $this->control->resume($request->reason, 'operator_api_approval', $approvedBy);
            $this->connection->table('money_movement_resume_requests')
                ->where('id', $request->id->value())
                ->update([
                    'status' => 'approved',
                    'approved_by' => $approvedBy->value(),
                    'approved_at' => $now,
                ]);

            return new MoneyMovementResumeRequest(
                $request->id,
                $request->requestedBy,
                $request->reason,
                'approved',
                $request->requestedAt,
                $request->expiresAt,
                $approvedBy,
                $now,
            );
        });
    }

    public function reject(Uuid $requestId, Uuid $rejectedBy, string $reason): MoneyMovementResumeRequest
    {
        $reason = $this->validatedReason($reason);

        return $this->close($requestId, $rejectedBy, $reason, 'rejected', false);
    }

    public function cancel(Uuid $requestId, Uuid $cancelledBy, string $reason): MoneyMovementResumeRequest
    {
        $reason = $this->validatedReason($reason);

        return $this->close($requestId, $cancelledBy, $reason, 'cancelled', true);
    }

    private function close(
        Uuid $requestId,
        Uuid $closedBy,
        string $reason,
        string $status,
        bool $requesterMustMatch,
    ): MoneyMovementResumeRequest {
        return $this->connection->transaction(function () use (
            $requestId,
            $closedBy,
            $reason,
            $status,
            $requesterMustMatch,
        ): MoneyMovementResumeRequest {
            $record = $this->connection->table('money_movement_resume_requests')
                ->where('id', $requestId->value())
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw InvalidMoneyMovementResume::requestNotFound();
            }

            $request = $this->hydrate($record);
            $sameOperator = $request->requestedBy->value() === $closedBy->value();

            if ($requesterMustMatch && ! $sameOperator) {
                throw InvalidMoneyMovementResume::onlyRequesterCanCancel();
            }
            if (! $requesterMustMatch && $sameOperator) {
                throw InvalidMoneyMovementResume::sameOperatorCannotReject();
            }

            $now = $this->utcNow();

            if ($request->expiresAt <= $now) {
                throw InvalidMoneyMovementResume::expired();
            }

            $this->connection->table('money_movement_resume_requests')
                ->where('id', $request->id->value())
                ->update([
                    'status' => $status,
                    'closed_by' => $closedBy->value(),
                    'closure_reason' => $reason,
                    'closed_at' => $now,
                ]);

            return new MoneyMovementResumeRequest(
                $request->id,
                $request->requestedBy,
                $request->reason,
                $status,
                $request->requestedAt,
                $request->expiresAt,
                closedBy: $closedBy,
                closureReason: $reason,
                closedAt: $now,
            );
        });
    }

    private function lockSuspendedControl(): void
    {
        $control = $this->connection->table('money_movement_controls')
            ->where('name', 'global')
            ->lockForUpdate()
            ->first();

        if ($control === null || filter_var($control->enabled, FILTER_VALIDATE_BOOL)) {
            throw InvalidMoneyMovementResume::whileEnabled();
        }
    }

    private function hydrate(object $record): MoneyMovementResumeRequest
    {
        return new MoneyMovementResumeRequest(
            new Uuid((string) $record->id),
            new Uuid((string) $record->requested_by),
            (string) $record->reason,
            (string) $record->status,
            new DateTimeImmutable((string) $record->requested_at, new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $record->expires_at, new DateTimeZone('UTC')),
            $record->approved_by === null ? null : new Uuid((string) $record->approved_by),
            $record->approved_at === null
                ? null
                : new DateTimeImmutable((string) $record->approved_at, new DateTimeZone('UTC')),
            $record->closed_by === null ? null : new Uuid((string) $record->closed_by),
            $record->closure_reason === null ? null : (string) $record->closure_reason,
            $record->closed_at === null
                ? null
                : new DateTimeImmutable((string) $record->closed_at, new DateTimeZone('UTC')),
        );
    }

    private function validatedReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('The operational reason must contain between 10 and 255 characters.');
        }

        return $reason;
    }

    private function utcNow(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }
}
