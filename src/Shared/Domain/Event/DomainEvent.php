<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

use DateTimeImmutable;
use InvalidArgumentException;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Identifier;
use Shared\Domain\Identifier\Uuid;

abstract readonly class DomainEvent
{
    public function __construct(
        private Uuid $eventId,
        private Identifier $aggregateId,
        private int $aggregateVersion,
        private DateTimeImmutable $occurredOn,
        private ?CorrelationId $correlationId = null,
    ) {
        if ($aggregateVersion < 1) {
            throw new InvalidArgumentException('An event aggregate version must be at least 1.');
        }
    }

    /**
     * Returns the name of the event.
     * @return string
     */
    abstract public static function eventName(): string;

    /**
     * Returns the unique identifier of the event.
     * @return Uuid
     */
    final public function eventId(): Uuid
    {
        return $this->eventId;
    }

    /**
     * Returns the identifier of the aggregate that produced the event.
     * @return Identifier
     */
    final public function aggregateId(): Identifier
    {
        return $this->aggregateId;
    }

    /**
     * Returns the version of the aggregate at the time the event occurred.
     * @return int
     */
    final public function aggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    /**
     * Returns the timestamp when the event occurred.
     * @return \DateTimeImmutable
     */
    final public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    /**
     * Returns the correlation identifier of the event, if any.
     * @return CorrelationId|null
     */
    final public function correlationId(): ?CorrelationId
    {
        return $this->correlationId;
    }

    /**
     * Returns the payload of the event as an associative array.
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [];
    }
}
