<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

trait RecordDomainEvents
{
    /**
     * @var list<DomainEvent>
     */
    private array $recordedEvents = [];

    protected function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * @return list<DomainEvent>
     */
    public function recordedEvents(): array
    {
        return $this->recordedEvents;
    }

    /**
     * @return bool
     */
    public function hasRecordedEvents(): bool
    {
        return !empty($this->recordedEvents);
    }
}
