<?php

declare(strict_types=1);

namespace Audit\Domain\Log;

use DateTimeImmutable;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** A permanent description of one important change made in the banking system. */
final readonly class AuditLogEntry
{
    /**
     * @param  array<string, mixed>  $payload  The business details supplied by the domain event.
     */
    public function __construct(
        private Uuid $eventId,
        private string $eventName,
        private string $aggregateType,
        private string $aggregateId,
        private int $aggregateVersion,
        private ?CorrelationId $correlationId,
        private array $payload,
        private DateTimeImmutable $occurredOn,
        private DateTimeImmutable $recordedAt,
    ) {}

    public function eventId(): Uuid
    {
        return $this->eventId;
    }

    public function eventName(): string
    {
        return $this->eventName;
    }

    public function aggregateType(): string
    {
        return $this->aggregateType;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function aggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    public function correlationId(): ?CorrelationId
    {
        return $this->correlationId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
