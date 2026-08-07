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

    public function hasRecordedEvents(): bool
    {
        return !empty($this->recordedEvents);
    }
}
