<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

interface DomainEvent
{
    /**
     * Returns the unique identifier of the event.
     * @return string
     */
    public function eventId(): string;

    /**
     * Returns the name of the event.
     * @return string
     */
    public function eventName(): string;

    /**
     * Returns the timestamp when the event occurred.
     * @return \DateTimeImmutable
     */
    public function occurredOn(): \DateTimeImmutable;
}
