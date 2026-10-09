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
        return $this->connection->table('money_movement_resume_requests')
            ->where('status', 'pending')
            ->where('expires_at', '<=', $this->clock->now()->setTimezone(new DateTimeZone('UTC')))
            ->update(['status' => 'expired']);
    }
}
