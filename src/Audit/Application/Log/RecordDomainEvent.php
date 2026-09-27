<?php

declare(strict_types=1);

namespace Audit\Application\Log;

use Audit\Domain\Log\AuditLogEntry;
use Audit\Domain\Log\Repository\AuditLogRepository;
use Shared\Contracts\Clock;
use Shared\Domain\Event\DomainEvent;

/** Converts a domain event into a permanent audit record. */
final readonly class RecordDomainEvent
{
    public function __construct(
        private AuditLogRepository $auditLogs,
        private Clock $clock,
    ) {}

    public function handle(DomainEvent $event): void
    {
        $aggregateId = $event->aggregateId();

        $this->auditLogs->append(new AuditLogEntry(
            $event->eventId(),
            $event::eventName(),
            $aggregateId::class,
            $aggregateId->value(),
            $event->aggregateVersion(),
            $event->correlationId(),
            $event->payload(),
            $event->occurredOn(),
            $this->clock->now(),
        ));
    }
}
