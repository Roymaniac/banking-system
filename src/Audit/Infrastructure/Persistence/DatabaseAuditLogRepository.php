<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Persistence;

use Audit\Domain\Log\AuditLogEntry;
use Audit\Domain\Log\Repository\AuditLogRepository;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;

/** Stores audit history in the database as append-only rows. */
final readonly class DatabaseAuditLogRepository implements AuditLogRepository
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function append(AuditLogEntry $entry): void
    {
        $this->connection->table('audit_log')->insertOrIgnore([
            'event_id' => $entry->eventId()->value(),
            'event_name' => $entry->eventName(),
            'aggregate_type' => $entry->aggregateType(),
            'aggregate_id' => $entry->aggregateId(),
            'aggregate_version' => $entry->aggregateVersion(),
            'correlation_id' => $entry->correlationId()?->value(),
            'payload' => json_encode($entry->payload(), JSON_THROW_ON_ERROR),
            'occurred_on' => $entry->occurredOn()->setTimezone(new DateTimeZone('UTC')),
            'recorded_at' => $entry->recordedAt()->setTimezone(new DateTimeZone('UTC')),
        ]);
    }
}
