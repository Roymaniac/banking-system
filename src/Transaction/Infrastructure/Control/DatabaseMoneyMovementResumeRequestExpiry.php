<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Control;

use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Shared\Contracts\Clock;
use Transaction\Application\Control\MoneyMovementResumeRequestExpiry;

/** Makes elapsed approval windows explicit instead of leaving stale requests pending. */
final readonly class DatabaseMoneyMovementResumeRequestExpiry implements MoneyMovementResumeRequestExpiry
{
    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function expire(): int
    {
        return $this->connection->transaction(function (): int {
            $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
            $expired = $this->connection->table('money_movement_resume_requests')
                ->where('status', 'pending')
                ->where('expires_at', '<=', $now)
                ->update(['status' => 'expired']);

            $control = $this->connection->table('money_movement_controls')
                ->where('name', 'global')
                ->first();

            if ($control === null) {
                return $expired;
            }

            $superseded = $this->connection->table('money_movement_resume_requests')
                ->where('status', 'pending')
                ->where(function ($query) use ($control): void {
                    $query->whereNull('control_revision')
                        ->orWhere('control_revision', '<>', (int) $control->revision);
                })
                ->update([
                    'status' => 'superseded',
                    'closure_reason' => 'A newer suspension replaced this approval request.',
                    'closed_at' => $now,
                ]);

            return $expired + $superseded;
        });
    }
}
