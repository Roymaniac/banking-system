<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Health;

use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Shared\Contracts\Clock;

/** Records proof that Laravel's scheduler is actively running. */
final readonly class RecordSchedulerHeartbeat
{
    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function record(): void
    {
        $this->connection->table('system_heartbeats')
            ->updateOrInsert(
                ['name' => 'scheduler'],
                ['recorded_at' => $this->clock->now()->setTimezone(new DateTimeZone('UTC'))],
            );
    }
}
