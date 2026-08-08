<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

trait RecordsDomainEvents
{
    /**
     * A list of domain events that have been recorded by the aggregate.
     * @var list<DomainEvent>
     */
    private array $recordedEvents = [];

    final protected function recordDomainEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Pulls all recorded domain events and clears the list of recorded events.
     * @return list<DomainEvent>
     */
    final public function pullDomainEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * Returns the list of recorded domain events without clearing the list.
     * @return list<DomainEvent>
     */
    final public function recordedEvents(): array
    {
        return $this->recordedEvents;
    }

    /**
     * Checks if there are any recorded domain events.
     * @return bool
     */
    final public function hasRecordedEvents(): bool
    {
        return !empty($this->recordedEvents);
    }
}
