<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Persistence;

use Audit\Domain\Activity\ActivityLogEntry;
use Audit\Domain\Activity\Repository\ActivityLogRepository;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;

/** Persists user activity as append-only database rows. */
final readonly class DatabaseActivityLogRepository implements ActivityLogRepository
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function append(ActivityLogEntry $activity): void
    {
        $this->connection->table('activity_log')
            ->insert([
                'id' => $activity->id()->value(),
                'actor_type' => $activity->actorType(),
                'actor_id' => $activity->actorId(),
                'action' => $activity->action(),
                'http_method' => $activity->httpMethod(),
                'response_status' => $activity->responseStatus(),
                'ip_address' => $activity->ipAddress(),
                'user_agent' => $activity->userAgent(),
                'metadata' => json_encode($activity->metadata(), JSON_THROW_ON_ERROR),
                'occurred_on' => $activity->occurredOn()->setTimezone(new DateTimeZone('UTC')),
            ]);
    }
}
