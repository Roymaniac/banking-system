<?php

declare(strict_types=1);

namespace Shared\Domain\Aggregate;

use DomainException;
use Shared\Domain\Entity\Entity;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\RecordsDomainEvents;

abstract class AggregateRoot extends Entity
{
    use RecordsDomainEvents;

    /**
     * The current version of the aggregate.
     * @var int
     */
    private int $version = 0;

    /**
     * Returns the current version of the aggregate.
     * @return int
     */
    final public function version(): int
    {
        return $this->version;
    }

    /**
     * Records a domain event for the aggregate.
     * @param DomainEvent $event
     * @throws DomainException if the event does not belong to the aggregate or if the event version is invalid.
     */
    final protected function record(DomainEvent $event): void
    {
        if (! $this->id()->equals($event->aggregateId())) {
            throw new DomainException('A domain event must belong to the aggregate that records it.');
        }

        if ($event->aggregateVersion() !== $this->version + 1) {
            throw new DomainException('A domain event version must immediately follow the aggregate version.');
        }

        $this->recordDomainEvent($event);
        $this->version++;
    }

    /**
     * Reconstitutes the aggregate at a specific version.
     * @param int $version
     * @throws DomainException if the version is negative.
     */
    final protected function reconstituteAtVersion(int $version): void
    {
        if ($version < 0) {
            throw new DomainException('An aggregate version cannot be negative.');
        }

        $this->version = $version;
    }
}
