<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Persistence;

use Audit\Domain\Security\Repository\SecurityEventRepository;
use Audit\Domain\Security\SecurityEvent;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;

/** Stores security events as append-only rows for later investigation. */
final readonly class DatabaseSecurityEventRepository implements SecurityEventRepository
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function append(SecurityEvent $event): void
    {
        $this->connection->table('security_events')->insert([
            'id' => $event->id()->value(),
            'type' => $event->type()->value,
            'severity' => $event->severity()->value,
            'subject_id' => $event->subjectId(),
            'subject_fingerprint' => $event->subjectFingerprint(),
            'ip_address' => $event->ipAddress(),
            'user_agent' => $event->userAgent(),
            'details' => json_encode($event->details(), JSON_THROW_ON_ERROR),
            'occurred_on' => $event->occurredOn()->setTimezone(new DateTimeZone('UTC')),
        ]);
    }
}
